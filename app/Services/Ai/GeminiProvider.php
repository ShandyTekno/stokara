<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProvider
{
    public const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $timeout = 60,
        private readonly int $maxTokens = 1024,
    ) {}

    public function reply(string $system, array $messages, array $context): AiReply
    {
        $contents = [];

        foreach ($messages as $message) {
            $role = $message['role'] === 'assistant' ? 'model' : 'user';

            $contents[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $message['content']],
                ],
            ];
        }

        if (empty($contents)) {
            $contents[] = [
                'role' => 'user',
                'parts' => [
                    ['text' => 'Halo'],
                ],
            ];
        }

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
            ])
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->post(self::ENDPOINT . $this->model . ':generateContent', [
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => $system],
                        ],
                    ],
                    'contents' => $contents,
                    'generationConfig' => [
                        'maxOutputTokens' => $this->maxTokens,
                    ],
                ]);
        } catch (ConnectionException $e) {
            Log::warning('AI: gagal terhubung ke Gemini.', [
                'exception' => $e::class,
            ]);

            throw new AiProviderException(
                'Layanan AI tidak dapat dihubungi atau terlalu lama merespons. Coba lagi sebentar lagi.'
            );
        }

        if ($response->failed()) {
            $errorMsg = (string) $response->json('error.message');

            Log::warning('AI: Gemini mengembalikan error.', [
                'status' => $response->status(),
                'message' => $errorMsg,
            ]);

            throw new AiProviderException(
                $this->messageForStatus($response->status(), $errorMsg)
            );
        }

        $text = trim(
            collect($response->json('candidates.0.content.parts') ?? [])
                ->pluck('text')
                ->filter()
                ->implode("\n\n")
        );

        if ($text === '') {
            throw new AiProviderException(
                'AI tidak memberikan jawaban. Coba ubah atau persingkat pertanyaan Anda.'
            );
        }

        $finishReason = $response->json('candidates.0.finishReason');

        if ($finishReason === 'MAX_TOKENS') {
            $text .= "\n\n(Jawaban terpotong karena batas panjang. Persempit pertanyaan atau minta lanjutan.)";
        }

        return new AiReply($text, false, [
            'provider' => 'gemini',
            'model' => $this->model,
            'input_tokens' => $response->json('usageMetadata.promptTokenCount'),
            'output_tokens' => $response->json('usageMetadata.candidatesTokenCount'),
            'stop_reason' => $finishReason,
        ]);
    }

    private function messageForStatus(int $status, string $errorMessage = ''): string
    {
        if (str_contains(strtolower($errorMessage), 'free tier is not available')) {
            return 'Project Google Cloud ini tidak mendukung tier gratis Gemini. Buat API key baru di aistudio.google.com/app/apikey dengan memilih opsi "Create API key in new project".';
        }

        return match (true) {
            in_array($status, [401, 403], true) =>
                'Layanan AI menolak akses. Periksa GEMINI_API_KEY di file .env.',
            $status === 404 =>
                'Model AI tidak ditemukan. Periksa GEMINI_MODEL di file .env.',
            $status === 400 =>
                'Permintaan ke layanan AI ditolak. Periksa nama model atau persingkat pertanyaan.',
            $status === 429 =>
                'Layanan AI sedang sibuk atau kuota habis. Coba lagi beberapa saat lagi.',
            $status >= 500 =>
                'Layanan AI sedang bermasalah. Coba lagi beberapa saat lagi.',
            default =>
                'Layanan AI tidak dapat memproses permintaan saat ini.',
        };
    }
}
