@props(['product'])

@php $variant = $product->defaultVariant(); @endphp

<article {{ $attributes->merge(['class' => 'group reveal flex flex-col overflow-hidden rounded-3xl border border-navy-100 bg-white shadow-lift transition duration-300 hover:-translate-y-1 hover:shadow-lift-lg']) }}>

    <a href="{{ $product->url() }}" class="relative block aspect-[16/10] overflow-hidden bg-navy-900">
        <img src="{{ $product->coverUrl() }}" alt=""
             class="h-full w-full object-cover opacity-80 transition duration-500 group-hover:scale-105 group-hover:opacity-100"
             loading="lazy" decoding="async">
        <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-navy-950/10 to-transparent"></div>

        <span class="absolute top-4 left-4 rounded-full bg-white/95 px-3 py-1 text-[11px] font-bold tracking-wide text-navy-900 uppercase">
            {{ $product->badgeLabel() }}
        </span>

        <h3 class="absolute right-5 bottom-4 left-5 font-[family-name:var(--font-display)] text-xl leading-tight font-semibold text-white">
            {{ $product->name() }}
        </h3>
    </a>

    <div class="flex flex-1 flex-col gap-4 p-6">
        <p class="flex-1 text-sm leading-relaxed text-navy-600">{{ $product->tagline() }}</p>

        <x-price :variant="$variant" size="sm" />

        <div class="flex items-center gap-2 pt-1">
            <a href="{{ $product->url() }}" class="btn-navy flex-1 px-4 py-2.5 text-xs">
                {{ __('site.product.learn_more') }}
            </a>

            {{-- Atalho direto para o checkout da Hotmart, só quando o produto
                 tem uma variante única e a oferta já está cadastrada. Com
                 variantes, a escolha precisa acontecer na página do produto. --}}
            @if (! $product->hasVariants() && $variant->isSellable())
                <a href="{{ $variant->checkoutUrl() }}" rel="noopener"
                   class="btn-primary shrink-0 px-4 py-2.5 text-xs">
                    {{ __('site.product.buy') }}
                </a>
            @endif
        </div>
    </div>
</article>
