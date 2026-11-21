<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\Ai\AiContextBuilder;
use App\Services\Ai\DemoProvider;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'sk-test-secret-123';

    private Store $store;
    private User $admin;
    private User $karyawan;
    private Product $minyak;
    private Product $garam;
    private Product $beras;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.gemini.key' => null,
            'services.gemini.model' => 'gemini-2.5-flash',
            'services.gemini.max_tokens' => 1024,
            'services.gemini.rate_per_minute' => 100,
            'services.gemini.daily_limit' => 0,
        ]);

        $this->store = Store::factory()->create(['name' => 'Toko Berkah']);
        $this->admin = User::factory()->create(['store_id' => $this->store->id]);
        $this->karyawan = User::factory()->karyawan()->create(['store_id' => $this->store->id]);

        $make = fn (string $name, int $stock) => Product::factory()->create([
            'store_id' => $this->store->id,
            'name' => $name,
            'stock' => $stock,
            'min_stock' => 10,
            'unit' => 'pcs',
            'pack_unit' => null,
            'units_per_pack' => 1,
            'purchase_price' => 1000,
            'selling_price' => 1500,
        ]);

        $this->minyak = $make('Minyak Goreng', 5);   // menipis
        $this->garam = $make('Garam Halus', 0);      // habis
        $this->beras = $make('Beras Premium', 100);  // aman
    }

    // ---------------------------------------------------------------- bantuan

    private function liveMode(): void
    {
        config(['services.gemini.key' => self::SECRET]);
    }

    private function fakeGemini(string $text = 'Jawaban uji', array $extra = []): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(array_merge([
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => 120,
                'candidatesTokenCount' => 30,
            ],
        ], $extra), 200)]);
    }

    private function ask(string $message, ?int $conversationId = null)
    {
        return $this->actingAs($this->admin)->post(route('ai.assistant.send'), [
            'message' => $message,
            'conversation_id' => $conversationId,
        ]);
    }

    private function conversation(User $user, ?Store $store = null, string $title = 'Percakapan'): AiConversation
    {
        $conversation = new AiConversation(['user_id' => $user->id, 'title' => $title]);
        $conversation->store_id = ($store ?? $this->store)->id;
        $conversation->save();

        return $conversation;
    }

    private function addMessage(AiConversation $conversation, string $role, string $content, bool $simulation = false): AiMessage
    {
        $message = new AiMessage([
            'ai_conversation_id' => $conversation->id,
            'role' => $role,
            'content' => $content,
            'is_simulation' => $simulation,
        ]);
        $message->store_id = $conversation->store_id;
        $message->save();

        return $message;
    }

    private function messages()
    {
        return AiMessage::withoutGlobalScopes()->orderBy('id')->get();
    }

    private function sell(Product $product, int $qty): void
    {
        app(StockService::class)->recordSale([
            'payment_method' => 'qris',
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
        ], $this->admin);
    }

    // ------------------------------------------------------------ akses halaman

    public function test_page_opens_in_demo_mode_without_api_key(): void
    {
        $this->actingAs($this->admin)->get(route('ai.assistant'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AiAssistant/Index')
                ->where('mode', 'demo')
                ->where('model', null)
                ->where('conversation', null)
                ->has('conversations', 0)
                ->has('messages', 0)
                ->has('suggestions', 6)
                ->where('limits.max_length', 1000));
    }

    public function test_page_reports_live_mode_when_key_is_configured(): void
    {
        $this->liveMode();

        $this->actingAs($this->admin)->get(route('ai.assistant'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'live')
                ->where('model', 'gemini-2.5-flash'));
    }

    public function test_karyawan_and_guest_cannot_use_the_assistant(): void
    {
        $conversation = $this->conversation($this->admin);

        $this->get(route('ai.assistant'))->assertRedirect('/login');
        $this->post(route('ai.assistant.send'), ['message' => 'Halo'])->assertRedirect('/login');

        $this->actingAs($this->karyawan);
        $this->get(route('ai.assistant'))->assertForbidden();
        $this->get(route('ai.assistant.show', $conversation))->assertForbidden();
        $this->post(route('ai.assistant.send'), ['message' => 'Halo'])->assertForbidden();
        $this->delete(route('ai.assistant.destroy', $conversation))->assertForbidden();

        $this->assertDatabaseCount('ai_messages', 0);
    }

    // -------------------------------------------------------------- mode demo

    public function test_demo_reply_is_labeled_simulation_and_uses_real_store_data(): void
    {
        Http::fake();

        $response = $this->ask('Barang apa yang stoknya hampir habis?');

        $conversation = AiConversation::withoutGlobalScopes()->firstOrFail();
        $response->assertSessionHasNoErrors()
            ->assertRedirect(route('ai.assistant.show', $conversation));

        $messages = $this->messages();
        $this->assertCount(2, $messages);

        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('Barang apa yang stoknya hampir habis?', $messages[0]->content);
        $this->assertFalse($messages[0]->is_simulation);

        $reply = $messages[1];
        $this->assertSame('assistant', $reply->role);
        $this->assertTrue($reply->is_simulation);
        $this->assertSame('demo', $reply->meta['provider']);
        $this->assertStringContainsString('[SIMULASI]', $reply->content);
        $this->assertStringContainsString('bukan AI sungguhan', $reply->content);
        $this->assertStringContainsString('Minyak Goreng', $reply->content);
        $this->assertStringContainsString('stok 5 pcs', $reply->content);
        $this->assertStringContainsString('Garam Halus', $reply->content);
        $this->assertStringContainsString('stok 0 pcs', $reply->content);
        $this->assertStringNotContainsString('Beras Premium', $reply->content);

        Http::assertNothingSent();
    }

    public function test_demo_reply_does_not_include_other_stores_data(): void
    {
        $other = Store::factory()->create();
        Product::factory()->habis()->create(['store_id' => $other->id, 'name' => 'Rahasia Pesaing']);

        $this->ask('Barang apa yang stoknya hampir habis?');

        $this->assertStringNotContainsString('Rahasia Pesaing', $this->messages()->last()->content);
    }

    public function test_demo_says_data_is_insufficient_without_sales(): void
    {
        $this->ask('Produk apa yang paling laris minggu ini?');

        $reply = $this->messages()->last();
        $this->assertTrue($reply->is_simulation);
        $this->assertStringContainsString('belum mencukupi', $reply->content);
    }

    public function test_demo_best_sellers_use_real_sales_data(): void
    {
        $this->sell($this->beras, 30);

        $this->ask('Produk apa yang paling laris minggu ini?');

        $content = $this->messages()->last()->content;
        $this->assertStringContainsString('Beras Premium', $content);
        $this->assertStringContainsString('terjual 30 pcs', $content);
        $this->assertStringContainsString('Rp 45.000', $content);
    }

    public function test_demo_provider_answers_every_example_question(): void
    {
        $this->sell($this->beras, 30);

        $context = app(AiContextBuilder::class)->build($this->store->id);
        $ask = fn (string $question) => (new DemoProvider())
            ->reply('', [['role' => 'user', 'content' => $question]], $context);

        $reply = $ask('Barang apa yang stoknya hampir habis?');
        $this->assertTrue($reply->isSimulation);
        $this->assertStringContainsString('Minyak Goreng', $reply->text);

        $this->assertStringContainsString('Beras Premium', $ask('Produk apa yang paling laris minggu ini?')->text);
        $this->assertStringContainsString('Minyak Goreng', $ask('Barang apa yang jarang terjual?')->text);
        $this->assertStringContainsString('beli sekitar', $ask('Berapa jumlah stok yang perlu ditambah?')->text);
        $this->assertStringContainsString('3 jenis barang', $ask('Bagaimana kondisi persediaan toko saya?')->text);
        $this->assertStringContainsString('1 transaksi', $ask('Buatkan ringkasan penjualan bulan ini.')->text);

        foreach (['Barang apa yang stoknya hampir habis?', 'Berapa jumlah stok yang perlu ditambah?'] as $q) {
            $this->assertStringContainsString('[SIMULASI]', $ask($q)->text);
        }
    }

    public function test_demo_unknown_question_shows_help_not_invented_answers(): void
    {
        $this->ask('Siapa presiden pertama Indonesia?');

        $content = $this->messages()->last()->content;
        $this->assertStringContainsString('[SIMULASI]', $content);
        $this->assertStringContainsString('belum mengenali', $content);
    }

    // ------------------------------------------------------------- mode sungguhan

    public function test_live_mode_calls_gemini_with_store_context_and_saves_reply(): void
    {
        $this->liveMode();
        $this->fakeGemini('Stok Minyak Goreng tinggal 5 pcs.');

        $other = Store::factory()->create(['name' => 'Toko Pesaing']);
        Product::factory()->habis()->create(['store_id' => $other->id, 'name' => 'Rahasia Pesaing']);

        $this->ask('Barang apa yang stoknya hampir habis?')->assertSessionHasNoErrors();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            $body = $request->data();

            return str_contains($request->url(), 'generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent')
                && $request->method() === 'POST'
                && $request->hasHeader('x-goog-api-key', self::SECRET)
                && $body['generationConfig']['maxOutputTokens'] === 1024
                && str_contains($body['systemInstruction']['parts'][0]['text'], 'Toko Berkah')
                && str_contains($body['systemInstruction']['parts'][0]['text'], 'Minyak Goreng')
                && str_contains($body['systemInstruction']['parts'][0]['text'], 'Garam Halus')
                && str_contains($body['systemInstruction']['parts'][0]['text'], 'mengarang')
                && ! str_contains($body['systemInstruction']['parts'][0]['text'], 'Rahasia Pesaing')
                && ! str_contains($body['systemInstruction']['parts'][0]['text'], self::SECRET)
                && $body['contents'][0]['role'] === 'user'
                && $body['contents'][0]['parts'][0]['text'] === 'Barang apa yang stoknya hampir habis?';
        });

        $reply = $this->messages()->last();
        $this->assertSame('assistant', $reply->role);
        $this->assertFalse($reply->is_simulation);
        $this->assertSame('Stok Minyak Goreng tinggal 5 pcs.', $reply->content);
        $this->assertSame('gemini', $reply->meta['provider']);
        $this->assertSame(120, $reply->meta['input_tokens']);
        $this->assertSame(30, $reply->meta['output_tokens']);
    }

    public function test_api_key_never_reaches_the_frontend(): void
    {
        $this->liveMode();
        $this->fakeGemini();

        $this->ask('Halo');
        $conversation = AiConversation::withoutGlobalScopes()->firstOrFail();

        foreach ([route('ai.assistant'), route('ai.assistant.show', $conversation)] as $url) {
            $content = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString(self::SECRET, $content);
        }
    }

    public function test_provider_errors_show_friendly_message_and_save_nothing(): void
    {
        $this->liveMode();

        $statuses = [401, 403, 404, 429, 500, 503];
        $sequence = Http::sequence();
        foreach ($statuses as $status) {
            $sequence->push(['error' => ['message' => self::SECRET.' detail internal']], $status);
        }
        Http::fake(['generativelanguage.googleapis.com/*' => $sequence]);

        foreach ($statuses as $status) {
            $response = $this->ask('Halo');

            $response->assertSessionHasErrors('message');
            $error = session('errors')->first('message');
            $this->assertNotEmpty($error, "Status {$status} tanpa pesan error");
            $this->assertStringNotContainsString(self::SECRET, $error);
            $this->assertStringNotContainsString('detail internal', $error);

            $this->assertSame(0, AiMessage::withoutGlobalScopes()->count());
            $this->assertSame(0, AiConversation::withoutGlobalScopes()->count());
        }
    }

    public function test_connection_failure_is_handled(): void
    {
        $this->liveMode();
        Http::fake(['generativelanguage.googleapis.com/*' => fn () => throw new ConnectionException('cURL error 28: Operation timed out')]);

        $this->ask('Halo')->assertSessionHasErrors('message');

        $this->assertStringContainsString('tidak dapat dihubungi', session('errors')->first('message'));
        $this->assertSame(0, AiMessage::withoutGlobalScopes()->count());
    }

    public function test_empty_reply_is_treated_as_an_error(): void
    {
        $this->liveMode();
        $this->fakeGemini('', ['candidates' => []]);

        $this->ask('Halo')->assertSessionHasErrors('message');

        $this->assertSame(0, AiMessage::withoutGlobalScopes()->count());
    }

    public function test_truncated_reply_gets_a_notice(): void
    {
        $this->liveMode();
        $this->fakeGemini('Jawaban panjang yang terpotong', [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'Jawaban panjang yang terpotong'],
                        ],
                        'role' => 'model',
                    ],
                    'finishReason' => 'MAX_TOKENS',
                ],
            ],
        ]);

        $this->ask('Halo');

        $this->assertStringContainsString('terpotong karena batas panjang', $this->messages()->last()->content);
    }

    public function test_follow_up_sends_conversation_history_in_order(): void
    {
        $this->liveMode();
        $this->fakeGemini('Jawaban pertama');

        $this->ask('Barang apa yang stoknya hampir habis?');
        $conversation = AiConversation::withoutGlobalScopes()->firstOrFail();
        $this->ask('Dan yang sudah habis?', $conversation->id)->assertSessionHasNoErrors();

        $second = Http::recorded()->last()[0];
        $this->assertSame([
            ['role' => 'user', 'parts' => [['text' => 'Barang apa yang stoknya hampir habis?']]],
            ['role' => 'model', 'parts' => [['text' => 'Jawaban pertama']]],
            ['role' => 'user', 'parts' => [['text' => 'Dan yang sudah habis?']]],
        ], $second->data()['contents']);

        $this->assertDatabaseCount('ai_conversations', 1);
        $this->assertCount(4, $this->messages());
    }

    public function test_simulated_messages_are_excluded_from_live_history(): void
    {
        $this->ask('Barang apa yang stoknya hampir habis?'); // mode demo
        $conversation = AiConversation::withoutGlobalScopes()->firstOrFail();

        $this->liveMode();
        $this->fakeGemini();
        $this->ask('Berapa penjualan hari ini?', $conversation->id);

        $sent = Http::recorded()->last()[0]->data()['contents'];
        $this->assertCount(1, $sent);
        $this->assertSame('user', $sent[0]['role']);
        $this->assertSame("Barang apa yang stoknya hampir habis?\n\nBerapa penjualan hari ini?", $sent[0]['parts'][0]['text']);
        $this->assertStringNotContainsString('[SIMULASI]', $sent[0]['parts'][0]['text']);
    }

    public function test_history_sent_to_the_provider_is_trimmed_and_alternating(): void
    {
        $this->liveMode();
        $this->fakeGemini();

        $conversation = $this->conversation($this->admin);
        for ($i = 1; $i <= 30; $i++) {
            $this->addMessage($conversation, $i % 2 === 1 ? 'user' : 'assistant', "pesan {$i}");
        }

        $this->ask('Pesan baru', $conversation->id)->assertSessionHasNoErrors();

        $sent = Http::recorded()->last()[0]->data()['contents'];

        $this->assertCount(11, $sent); // 12 terakhir, lalu pesan assistant di awal dibuang
        $this->assertSame('user', $sent[0]['role']);
        $this->assertSame('Pesan baru', end($sent)['parts'][0]['text']);
        foreach ($sent as $i => $message) {
            $this->assertSame($i % 2 === 0 ? 'user' : 'model', $message['role']);
        }
    }

    // ------------------------------------------------------- validasi dan batas

    public function test_message_validation(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('ai.assistant.send'), ['message' => ''])->assertSessionHasErrors('message');
        $this->post(route('ai.assistant.send'), ['message' => str_repeat('a', 1001)])->assertSessionHasErrors('message');

        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_cannot_post_into_conversations_of_other_users_or_stores(): void
    {
        $otherStore = Store::factory()->create();
        $otherAdmin = User::factory()->create(['store_id' => $otherStore->id]);

        $foreign = $this->conversation($otherAdmin, $otherStore);
        $staffs = $this->conversation($this->karyawan);

        foreach ([$foreign, $staffs] as $conversation) {
            $this->ask('Halo', $conversation->id)->assertSessionHasErrors('conversation_id');
        }

        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_per_minute_rate_limit(): void
    {
        config(['services.gemini.rate_per_minute' => 2]);

        $this->ask('Halo 1')->assertSessionHasNoErrors();
        $this->ask('Halo 2')->assertSessionHasNoErrors();
        $this->ask('Halo 3')->assertSessionHasErrors('message');

        $this->assertStringContainsString('Terlalu banyak pesan', session('errors')->first('message'));
        $this->assertSame(2, AiMessage::withoutGlobalScopes()->where('role', 'user')->count());
    }

    public function test_daily_limit_applies_to_live_mode_only(): void
    {
        config(['services.gemini.daily_limit' => 1]);
        $this->liveMode();
        $this->fakeGemini();

        $this->ask('Halo 1')->assertSessionHasNoErrors();
        $this->ask('Halo 2')->assertSessionHasErrors('message');
        $this->assertStringContainsString('Batas harian', session('errors')->first('message'));
        $this->assertCount(2, $this->messages()); // hanya pasangan pertama

        // Mode demo tidak menimbulkan biaya, jadi tidak dibatasi.
        config(['services.gemini.key' => null]);
        $this->ask('Halo 3')->assertSessionHasNoErrors();
        $this->ask('Halo 4')->assertSessionHasNoErrors();
    }

    public function test_conversation_length_is_capped(): void
    {
        $conversation = $this->conversation($this->admin);
        $rows = [];
        for ($i = 0; $i < 200; $i++) {
            $rows[] = [
                'store_id' => $this->store->id,
                'ai_conversation_id' => $conversation->id,
                'role' => $i % 2 === 0 ? 'user' : 'assistant',
                'content' => 'x',
                'is_simulation' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('ai_messages')->insert($rows);

        $this->ask('Halo', $conversation->id)->assertSessionHasErrors('message');

        $this->assertStringContainsString('terlalu panjang', session('errors')->first('message'));
        $this->assertSame(200, AiMessage::withoutGlobalScopes()->count());
    }

    // ----------------------------------------------------- riwayat percakapan

    public function test_title_comes_from_the_first_message(): void
    {
        $this->ask(str_repeat('panjang ', 20));

        $title = AiConversation::withoutGlobalScopes()->firstOrFail()->title;
        $this->assertLessThanOrEqual(61, mb_strlen($title));
        $this->assertStringStartsWith('panjang panjang', $title);
    }

    public function test_index_lists_only_own_conversations_newest_first(): void
    {
        $older = $this->conversation($this->admin, null, 'Lama');
        $newer = $this->conversation($this->admin, null, 'Baru');
        $this->conversation($this->karyawan, null, 'Milik karyawan');
        $otherStore = Store::factory()->create();
        $this->conversation(User::factory()->create(['store_id' => $otherStore->id]), $otherStore, 'Toko lain');

        DB::table('ai_conversations')->where('id', $older->id)->update(['updated_at' => now()->subDay()]);

        $this->actingAs($this->admin)->get(route('ai.assistant'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('conversations', 2)
                ->where('conversations.0.id', $newer->id)
                ->where('conversations.1.id', $older->id));
    }

    public function test_show_returns_messages_in_order_and_only_for_the_owner(): void
    {
        $conversation = $this->conversation($this->admin, null, 'Obrolan');
        $this->addMessage($conversation, 'user', 'A');
        $this->addMessage($conversation, 'assistant', 'B', true);
        $this->addMessage($conversation, 'user', 'C');

        $this->actingAs($this->admin)->get(route('ai.assistant.show', $conversation))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('AiAssistant/Index')
                ->where('conversation.id', $conversation->id)
                ->has('messages', 3)
                ->where('messages.0.content', 'A')
                ->where('messages.1.content', 'B')
                ->where('messages.1.is_simulation', true)
                ->where('messages.2.content', 'C'));

        $staffs = $this->conversation($this->karyawan);
        $otherStore = Store::factory()->create();
        $foreign = $this->conversation(User::factory()->create(['store_id' => $otherStore->id]), $otherStore);

        $this->get(route('ai.assistant.show', $staffs))->assertNotFound();
        $this->get(route('ai.assistant.show', $foreign))->assertNotFound();
    }

    public function test_conversation_can_be_deleted_with_its_messages(): void
    {
        $conversation = $this->conversation($this->admin);
        $this->addMessage($conversation, 'user', 'A');
        $this->addMessage($conversation, 'assistant', 'B');

        $this->actingAs($this->admin)
            ->delete(route('ai.assistant.destroy', $conversation))
            ->assertRedirect(route('ai.assistant'));

        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
    }

    public function test_conversations_of_others_cannot_be_deleted(): void
    {
        $staffs = $this->conversation($this->karyawan);
        $otherStore = Store::factory()->create();
        $foreign = $this->conversation(User::factory()->create(['store_id' => $otherStore->id]), $otherStore);

        $this->actingAs($this->admin);
        $this->delete(route('ai.assistant.destroy', $staffs))->assertNotFound();
        $this->delete(route('ai.assistant.destroy', $foreign))->assertNotFound();

        $this->assertDatabaseCount('ai_conversations', 2);
    }
}
