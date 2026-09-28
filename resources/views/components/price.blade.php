@props([
    'variant',
    'size' => 'md',
])

@php
    use App\Support\Money;

    $sizes = [
        'sm' => ['price' => 'text-xl',           'was' => 'text-xs'],
        'md' => ['price' => 'text-3xl',          'was' => 'text-sm'],
        'lg' => ['price' => 'text-4xl sm:text-5xl', 'was' => 'text-base'],
    ][$size];
@endphp

{{--
    Sem simulação de parcela. O parcelamento é configurado por oferta dentro da
    Hotmart, e o site não tem como saber o número de parcelas nem se há juros —
    anunciar "12x sem juros" daqui seria chutar. O valor das parcelas aparece no
    checkout deles, que é onde a informação é verdadeira.
--}}
<div {{ $attributes->merge(['class' => 'space-y-1']) }}>

    @if ($variant->hasDiscount())
        <div class="flex items-center gap-2">
            <span class="{{ $sizes['was'] }} text-navy-500 line-through">{{ Money::format($variant->listPrice) }}</span>
            <span class="rounded-full bg-ember-100 px-2 py-0.5 text-[11px] font-bold text-ember-700">
                {{ __('site.product.off', ['percent' => round($variant->discountPercent())]) }}
            </span>
        </div>
    @endif

    <p class="{{ $sizes['price'] }} font-bold tracking-tight text-navy-950">
        {{ Money::format($variant->price) }}
    </p>
</div>
