<?php

namespace App\Services\Ai;

interface AiProvider
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages  berselang-seling, dimulai dan diakhiri role user
     * @param  array<string, mixed>  $context  data toko hasil AiContextBuilder
     *
     * @throws AiProviderException
     */
    public function reply(string $system, array $messages, array $context): AiReply;
}
