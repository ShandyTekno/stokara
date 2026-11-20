<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicProvider implements AiProvider
{
    public const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    public const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeout = 20,
        private readonly int $maxTokens = 1024,
    ) {}

    public function reply(string $system, array $messages, array $context): AiReply
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->post(self::ENDPOINT, [
                    'model' => $this->model,
                    'max_tokens' => $this->maxTokens,
                    'system' => $system,
                    'messages' => $messages,
                ]);
        } catch (ConnectionException $e) {
            // Hanya nama class yang dicatat; tidak ada isi pesan atau kunci.
            Log::warning('AI: gagal terhubung ke Anthropic.', ['exception' => $e::class]);

            throw new AiProviderException('Layanan AI tidak dapat dihubungi atau terlalu lama merespons. Coba lagi sebentar lagi.');
        }

        if ($response->failed()) {
            Log::warning('AI: Anthropic mengembalikan error.', [
                'status' => $response->status(),
                'type' => $response->json('error.type'),
            ]);

            throw new AiProviderException($this->messageForStatus($response->status()));
        }

        $text = trim(collect($response->json('content') ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n\n"));

        if ($text === '') {
            throw new AiProviderException('AI tidak memberikan jawaban. Coba ubah atau persingkat pertanyaan Anda.');
        }

        $stopReason = $response->json('stop_reason');
        if ($stopReason === 'max_tokens') {
            $text .= "\n\n(Jawaban terpotong karena batas panjang. Persempit pertanyaan atau minta lanjutan.)";
        }

        return new AiReply($text, false, [
            'provider' => 'anthropic',
            'model' => $response->json('model') ?? $this->model,
            'input_tokens' => $response->json('usage.input_tokens'),
            'output_tokens' => $response->json('usage.output_tokens'),
            'stop_reason' => $stopReason,
        ]);
    }

    private function messageForStatus(int $status): string
    {
        return match (true) {
            in_array($status, [401, 403], true) => 'Layanan AI menolak akses. Periksa ANTHROPIC_API_KEY di file .env.',
            $status === 404 => 'Model AI tidak ditemukan. Periksa ANTHROPIC_MODEL di file .env.',
            $status === 400 => 'Permintaan ke layanan AI ditolak. Periksa nama model dan saldo akun Anthropic, atau persingkat pertanyaan.',
            $status === 429 => 'Layanan AI sedang sibuk atau kuota habis. Coba lagi beberapa saat lagi.',
            $status >= 500 => 'Layanan AI sedang bermasalah. Coba lagi beberapa saat lagi.',
            default => 'Layanan AI tidak dapat memproses permintaan saat ini.',
        };
    }
}
