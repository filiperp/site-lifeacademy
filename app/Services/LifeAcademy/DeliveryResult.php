<?php

namespace App\Services\LifeAcademy;

class DeliveryResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly string $message,
        public readonly string $body,
    ) {}
}
