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

            @unless ($product->hasVariants())
                <form method="POST" action="{{ route('cart.add') }}" class="shrink-0">
                    @csrf
                    <input type="hidden" name="product" value="{{ $product->key }}">
                    <input type="hidden" name="variant" value="{{ $variant->key }}">
                    <button type="submit" class="btn-outline p-2.5" aria-label="{{ __('site.product.add_to_cart') }}">
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path d="M2.5 2.5h1.8l1.9 9.5h8.6l1.7-6.7H5.2" stroke-linecap="round" stroke-linejoin="round"/>
                            <circle cx="7.5" cy="16" r="1.25"/><circle cx="14.5" cy="16" r="1.25"/>
                        </svg>
                    </button>
                </form>
            @endunless
        </div>
    </div>
</article>
