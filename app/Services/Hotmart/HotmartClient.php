<?php

namespace App\Services\Hotmart;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Cliente da API da Hotmart.
 *
 * Não participa da venda — a compra acontece inteira no checkout da Hotmart e
 * chega aqui pelo webhook. Serve para sincronizar preços das ofertas e para
 * reconferir uma transação quando o payload do webhook parecer incompleto.
 */
class HotmartClient
{
    public function isConfigured(): bool
    {
        return filled($this->basicToken());
    }

    /**
     * Consulta uma venda pelo código da transação.
     *
     * @throws HotmartException
     */
    public function findSale(string $transaction): ?array
    {
        $response = $this->request()
            ->get(config('hotmart.endpoints.payments').'/sales/history', [
                'transaction' => $transaction,
            ]);

        if ($response->failed()) {
            throw HotmartException::fromResponse($response->status(), $response->json() ?? []);
        }

        return $response->json('items.0');
    }

    /**
     * Lista as ofertas de um produto, com os preços praticados.
     *
     * @throws HotmartException
     */
    public function offers(string $productId): array
    {
        $response = $this->request()
            ->get(config('hotmart.endpoints.products')."/products/{$productId}/offers");

        if ($response->failed()) {
            throw HotmartException::fromResponse($response->status(), $response->json() ?? []);
        }

        return (array) $response->json('items', []);
    }

    /** @throws HotmartException */
    public function products(): array
    {
        $response = $this->request()
            ->get(config('hotmart.endpoints.products').'/products');

        if ($response->failed()) {
            throw HotmartException::fromResponse($response->status(), $response->json() ?? []);
        }

        return (array) $response->json('items', []);
    }

    /**
     * Token OAuth2 via client_credentials.
     *
     * Fica em cache até pouco antes de expirar: a Hotmart devolve tokens de
     * longa duração e pedir um novo a cada chamada é desperdício.
     *
     * @throws HotmartException
     */
    public function accessToken(): string
    {
        return Cache::remember('hotmart.access_token', now()->addMinutes(50), function () {
            $response = Http::asForm()
                ->withHeaders(['Authorization' => 'Basic '.$this->basicToken()])
                ->timeout(config('hotmart.timeout'))
                ->post(config('hotmart.endpoints.token'), [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => config('hotmart.client_id'),
                    'client_secret' => config('hotmart.client_secret'),
                ]);

            if ($response->failed()) {
                throw HotmartException::fromResponse($response->status(), $response->json() ?? []);
            }

            $token = $response->json('access_token');

            if (! $token) {
                throw new HotmartException('A Hotmart não devolveu access_token.');
            }

            return $token;
        });
    }

    private function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw HotmartException::notConfigured();
        }

        return Http::withToken($this->accessToken())
            ->acceptJson()
            ->timeout(config('hotmart.timeout'))
            ->retry(2, 300, throw: false);
    }

    /**
     * O painel entrega um Basic pronto; se não estiver no .env, derivamos de
     * client_id:client_secret, que é como ele é montado.
     */
    private function basicToken(): ?string
    {
        if ($basic = config('hotmart.basic')) {
            return $basic;
        }

        $id = config('hotmart.client_id');
        $secret = config('hotmart.client_secret');

        if (! $id || ! $secret) {
            return null;
        }

        return base64_encode($id.':'.$secret);
    }
}
