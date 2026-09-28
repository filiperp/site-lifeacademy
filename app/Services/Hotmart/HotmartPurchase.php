<?php

namespace App\Services\Hotmart;

use Illuminate\Support\Arr;

/**
 * Normaliza o payload do webhook da Hotmart.
 *
 * Todo o resto do sistema fala com esta classe, nunca com o array cru. É de
 * propósito: o formato mudou entre a versão 1.x (campos achatados: `prod`,
 * `email`, `off`) e a 2.0 (aninhado em `data.product`, `data.buyer`,
 * `data.purchase`), e a documentação pública está atrás de um bloqueio.
 *
 * Cada campo lista os caminhos candidatos, do mais novo para o mais antigo.
 * Quando o payload real da conta estiver em mãos — basta um "Enviar teste" no
 * painel — só este arquivo precisa mudar.
 *
 * `raw()` guarda o payload inteiro, então nada se perde no caminho.
 */
class HotmartPurchase
{
    public function __construct(private readonly array $payload) {}

    public static function fromWebhook(array $payload): self
    {
        return new self($payload);
    }

    public function raw(): array
    {
        return $this->payload;
    }

    /** Identificador do evento, usado para idempotência. */
    public function eventId(): ?string
    {
        return $this->firstOf(['id', 'event_id']);
    }

    public function event(): string
    {
        return strtoupper((string) ($this->firstOf(['event', 'data.event']) ?? ''));
    }

    public function version(): ?string
    {
        return $this->firstOf(['version']);
    }

    /** Hottok no corpo — a Hotmart o inclui no "Enviar teste" do painel. */
    public function hottokInBody(): ?string
    {
        return $this->firstOf(['hottok', 'data.hottok']);
    }

    /** Código da transação. É a chave natural da compra. */
    public function transaction(): ?string
    {
        return $this->firstOf([
            'data.purchase.transaction',
            'data.transaction',
            'transaction',
        ]);
    }

    public function productCode(): ?string
    {
        $value = $this->firstOf([
            'data.product.id',
            'data.product.ucode',
            'prod',
            'product_id',
        ]);

        return $value === null ? null : (string) $value;
    }

    public function offerCode(): ?string
    {
        return $this->firstOf([
            'data.purchase.offer.code',
            'data.offer.code',
            'off',
            'offer',
        ]);
    }

    /**
     * SKU cadastrado na oferta. Quando preenchido com os codenames unidos por
     * "-" (o mesmo formato que o WooCommerce usava), dispensa o mapeamento por
     * código de oferta — é o caminho mais robusto, porque não quebra se alguém
     * recriar a oferta na Hotmart.
     */
    public function sku(): ?string
    {
        return $this->firstOf([
            'data.product.sku',
            'data.purchase.sku',
            'prod_sku',
            'sku',
        ]);
    }

    public function productName(): ?string
    {
        return $this->firstOf(['data.product.name', 'prod_name', 'product_name']);
    }

    public function buyerEmail(): ?string
    {
        return $this->firstOf(['data.buyer.email', 'buyer.email', 'email']);
    }

    public function buyerName(): ?string
    {
        return $this->firstOf(['data.buyer.name', 'buyer.name', 'name']);
    }

    public function buyerPhone(): ?string
    {
        $phone = $this->firstOf([
            'data.buyer.checkout_phone',
            'data.buyer.phone',
            'buyer.checkout_phone',
            'phone_number',
        ]);

        // A 2.0 pode separar DDI e número.
        $area = $this->firstOf(['data.buyer.checkout_phone_code', 'phone_local_code']);

        return $phone && $area ? $area.$phone : $phone;
    }

    public function buyerDocument(): ?string
    {
        return $this->firstOf(['data.buyer.document', 'data.buyer.doc', 'doc']);
    }

    /** Valor efetivamente pago. */
    public function price(): float
    {
        $value = $this->firstOf([
            'data.purchase.price.value',
            'data.purchase.full_price.value',
            'purchase.price.value',
            'price',
        ]);

        return (float) ($value ?? 0);
    }

    public function currency(): string
    {
        return strtoupper((string) ($this->firstOf([
            'data.purchase.price.currency_value',
            'data.purchase.price.currency_code',
            'currency',
        ]) ?? 'BRL'));
    }

    public function installments(): int
    {
        return (int) ($this->firstOf([
            'data.purchase.payment.installments_number',
            'purchase.payment.installments_number',
            'installments_number',
        ]) ?? 1);
    }

    public function paymentType(): ?string
    {
        return $this->firstOf([
            'data.purchase.payment.type',
            'purchase.payment.type',
            'payment_type',
        ]);
    }

    public function status(): ?string
    {
        $status = $this->firstOf(['data.purchase.status', 'status']);

        return $status ? strtoupper((string) $status) : null;
    }

    /**
     * Data de aprovação. A Hotmart manda epoch em milissegundos.
     */
    public function approvedAt(): ?\DateTimeInterface
    {
        $value = $this->firstOf([
            'data.purchase.approved_date',
            'data.purchase.order_date',
            'approved_date',
        ]);

        if (! $value) {
            return null;
        }

        // Epoch em milissegundos tem 13 dígitos; em segundos, 10.
        $seconds = strlen((string) (int) $value) > 11
            ? (int) ($value / 1000)
            : (int) $value;

        return (new \DateTimeImmutable)->setTimestamp($seconds);
    }

    /** Devolve o primeiro caminho presente e não vazio. */
    private function firstOf(array $paths): mixed
    {
        foreach ($paths as $path) {
            $value = Arr::get($this->payload, $path);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
