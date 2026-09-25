@props([
    'variant',
    'size' => 'md',
    'showInstallments' => true,
])

@php
    use App\Support\Money;

    $max = app(\App\Services\Asaas\CheckoutService::class)->maxInstallmentsFor($variant->price);
    $installment = Money::installment($variant->price, $max);
    $interestFree = config('asaas.installments.interest_bearer') === 'merchant';

    $sizes = [
        'sm' => ['price' => 'text-xl', 'was' => 'text-xs', 'note' => 'text-xs'],
        'md' => ['price' => 'text-3xl', 'was' => 'text-sm', 'note' => 'text-sm'],
        'lg' => ['price' => 'text-4xl sm:text-5xl', 'was' => 'text-base', 'note' => 'text-sm'],
    ][$size];
@endphp

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>

    @if ($variant->hasDiscount())
        <div class="flex items-center gap-2">
            <span class="{{ $sizes['was'] }} text-navy-400 line-through">{{ Money::format($variant->listPrice) }}</span>
            <span class="rounded-full bg-ember-100 px-2 py-0.5 text-[11px] font-bold text-ember-700">
                {{ __('site.product.off', ['percent' => round($variant->discountPercent())]) }}
            </span>
        </div>
    @endif

    <p class="{{ $sizes['price'] }} font-bold tracking-tight text-navy-950">
        {{ Money::format($variant->price) }}
    </p>

    @if ($showInstallments && config('asaas.installments.enabled') && $max > 1)
        <p class="{{ $sizes['note'] }} text-navy-600">
            {{ __($interestFree ? 'site.price.installments_free' : 'site.price.installments', [
                'count' => $max,
                'value' => Money::format($installment),
            ]) }}
        </p>
    @endif
</div>
