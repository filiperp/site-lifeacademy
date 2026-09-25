<?php

namespace App\Services\Asaas;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class AsaasClient
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly ?string $baseUrl = null,
    ) {}

    public function isConfigured(): bool
    {
        return filled($this->key());
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws AsaasException
     */
    public function createCheckout(array $payload): array
    {
        return $this->send('post', '/checkouts', $payload);
    }

    /** @throws AsaasException */
    public function getCheckout(string $id): array
    {
        return $this->send('get', "/checkouts/{$id}");
    }

    /** @throws AsaasException */
    public function getPayment(string $id): array
    {
        return $this->send('get', "/payments/{$id}");
    }

    /** @throws AsaasException */
    public function getInstallment(string $id): array
    {
        return $this->send('get', "/installments/{$id}");
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws AsaasException
     */
    private function send(string $method, string $path, array $payload = []): array
    {
        if (! $this->isConfigured()) {
            throw AsaasException::notConfigured();
        }

        $response = $this->request()->{$method}($path, $payload);

        if ($response->failed()) {
            throw AsaasException::fromResponse($response->status(), $response->json() ?? []);
        }

        return (array) $response->json();
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl ?? config('asaas.base_url'))
            ->withHeaders([
                'access_token' => $this->key(),
                'User-Agent'   => 'LifeAcademy-Site/1.0',
            ])
            ->acceptJson()
            ->asJson()
            ->timeout(config('asaas.timeout'))
            ->retry(2, 300, throw: false);
    }

    private function key(): ?string
    {
        return $this->apiKey ?? config('asaas.api_key');
    }
}
