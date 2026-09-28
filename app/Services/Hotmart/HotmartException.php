<?php

namespace App\Services\Hotmart;

use RuntimeException;

class HotmartException extends RuntimeException
{
    public int $status = 0;

    /** @var array<string, mixed> */
    public array $body = [];

    public static function notConfigured(): self
    {
        return new self('Credenciais da Hotmart ausentes (HOTMART_CLIENT_ID / HOTMART_CLIENT_SECRET).');
    }

    /** @param array<string, mixed> $body */
    public static function fromResponse(int $status, array $body): self
    {
        $message = $body['error_description']
            ?? $body['message']
            ?? $body['error']
            ?? "A Hotmart respondeu HTTP {$status}.";

        $e = new self(is_string($message) ? $message : "A Hotmart respondeu HTTP {$status}.");
        $e->status = $status;
        $e->body = $body;

        return $e;
    }
}
