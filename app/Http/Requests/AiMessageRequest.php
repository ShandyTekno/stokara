<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();

        return [
            'message' => ['required', 'string', 'max:1000'],
            'conversation_id' => [
                'nullable', 'integer',
                Rule::exists('ai_conversations', 'id')
                    ->where('store_id', $user->store_id)
                    ->where('user_id', $user->id),
            ],
        ];
    }

    public function attributes(): array
    {
        return ['message' => 'pesan', 'conversation_id' => 'percakapan'];
    }

    public function messages(): array
    {
        return [
            'message.required' => 'Tulis pertanyaan Anda terlebih dahulu.',
            'message.max' => 'Pesan terlalu panjang (maksimal :max karakter).',
            'conversation_id.exists' => 'Percakapan tidak ditemukan.',
            'conversation_id.integer' => 'Percakapan tidak valid.',
        ];
    }
}
