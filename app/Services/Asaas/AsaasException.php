<?php

namespace App\Services\Asaas;

use RuntimeException;

class AsaasException extends RuntimeException
{
    /** @var list<array{code: string, description: string}> */
    public array $errors = [];

    public int $status = 0;

    public static function notConfigured(): self
    {
        return new self('ASAAS_API_KEY não configurada.');
    }

    /** @param array<string, mixed> $body */
    public static function fromResponse(int $status, array $body): self
    {
        $errors = $body['errors'] ?? [];

        $message = collect($errors)
            ->pluck('description')
            ->filter()
            ->implode(' | ');

        $e = new self($message !== '' ? $message : "Asaas respondeu HTTP {$status}.");
        $e->status = $status;
        $e->errors = is_array($errors) ? $errors : [];

        return $e;
    }
}
