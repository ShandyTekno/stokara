<?php

namespace App\Http\Controllers;

use App\Http\Requests\AiMessageRequest;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\Ai\AiAssistantService;
use App\Services\Ai\AiProviderException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AiAssistantController extends Controller
{
    private const MAX_MESSAGES_PER_CONVERSATION = 200;
    private const SIDEBAR_LIMIT = 30;

    private const SUGGESTIONS = [
        'Barang apa yang stoknya hampir habis?',
        'Produk apa yang paling laris minggu ini?',
        'Barang apa yang jarang terjual?',
        'Berapa jumlah stok yang perlu ditambah?',
        'Bagaimana kondisi persediaan toko saya?',
        'Buatkan ringkasan penjualan bulan ini.',
    ];

    public function index(Request $request, AiAssistantService $ai): Response
    {
        return $this->render($request, $ai, null);
    }

    public function show(Request $request, AiAssistantService $ai, AiConversation $conversation): Response
    {
        $this->ensureOwner($request, $conversation);

        return $this->render($request, $ai, $conversation);
    }

    public function send(AiMessageRequest $request, AiAssistantService $ai): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $limiterKey = 'ai-chat:'.$user->id;
        $perMinute = (int) config('services.gemini.rate_per_minute', 10);
        if ($perMinute > 0 && RateLimiter::tooManyAttempts($limiterKey, $perMinute)) {
            $wait = RateLimiter::availableIn($limiterKey);

            return back()->withErrors(['message' => "Terlalu banyak pesan. Coba lagi dalam {$wait} detik."]);
        }

        if ($ai->isLive() && $ai->dailyLimitReached($user->store_id)) {
            return back()->withErrors(['message' => 'Batas harian penggunaan AI untuk toko ini sudah tercapai. Coba lagi besok.']);
        }

        $conversation = ! empty($data['conversation_id'])
            ? AiConversation::findOrFail($data['conversation_id'])
            : null;

        if ($conversation && $conversation->messages()->count() >= self::MAX_MESSAGES_PER_CONVERSATION) {
            return back()->withErrors(['message' => 'Percakapan ini sudah terlalu panjang. Mulai percakapan baru.']);
        }

        RateLimiter::hit($limiterKey, 60);

        $created = false;
        if ($conversation === null) {
            $conversation = AiConversation::create([
                'user_id' => $user->id,
                'title' => Str::limit($data['message'], 60, '…'),
            ]);
            $created = true;
        }

        $userMessage = $conversation->messages()->create([
            'role' => 'user',
            'content' => $data['message'],
            'is_simulation' => false,
        ]);

        try {
            $reply = $ai->respond($conversation, $user);
        } catch (AiProviderException $e) {
            return $this->fail($userMessage, $conversation, $created, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->fail($userMessage, $conversation, $created, 'Terjadi kesalahan saat menyusun jawaban. Coba lagi.');
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply->text,
            'is_simulation' => $reply->isSimulation,
            'meta' => $reply->meta,
        ]);
        $conversation->touch();

        return redirect()->route('ai.assistant.show', $conversation);
    }

    public function destroy(Request $request, AiConversation $conversation): RedirectResponse
    {
        $this->ensureOwner($request, $conversation);

        $conversation->delete(); // pesan ikut terhapus (cascade)

        return redirect()->route('ai.assistant')->with('success', 'Percakapan dihapus.');
    }

    /** Percakapan milik toko lain sudah 404 lewat BelongsToStore; di sini dibatasi ke pemiliknya. */
    private function ensureOwner(Request $request, AiConversation $conversation): void
    {
        abort_unless($conversation->user_id === $request->user()->id, 404);
    }

    private function fail(AiMessage $userMessage, AiConversation $conversation, bool $created, string $message): RedirectResponse
    {
        $userMessage->delete();
        if ($created) {
            $conversation->delete();
        }

        return back()->withErrors(['message' => $message]);
    }

    private function render(Request $request, AiAssistantService $ai, ?AiConversation $conversation): Response
    {
        $live = $ai->isLive();

        $conversations = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->latest('id')
            ->limit(self::SIDEBAR_LIMIT)
            ->get(['id', 'title', 'updated_at'])
            ->map(fn (AiConversation $c) => [
                'id' => $c->id,
                'title' => $c->title ?: 'Percakapan',
                'time' => $c->updated_at->diffForHumans(),
            ]);

        $messages = $conversation
            ? $conversation->messages()
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (AiMessage $m) => [
                    'id' => $m->id,
                    'role' => $m->role,
                    'content' => $m->content,
                    'is_simulation' => (bool) $m->is_simulation,
                    'time' => $m->created_at->format('d/m/Y H:i'),
                ])
            : collect();

        return Inertia::render('AiAssistant/Index', [
            'mode' => $live ? 'live' : 'demo',
            'model' => $live ? config('services.gemini.model') : null,
            'conversations' => $conversations,
            'conversation' => $conversation ? ['id' => $conversation->id, 'title' => $conversation->title] : null,
            'messages' => $messages,
            'suggestions' => self::SUGGESTIONS,
            'limits' => ['max_length' => 1000],
        ]);
    }
}
