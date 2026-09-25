@php
    $flashes = collect([
        'success' => ['bg' => 'bg-emerald-50 text-emerald-900 border-emerald-200', 'icon' => 'M5 10.5 8.5 14 15 7'],
        'warning' => ['bg' => 'bg-amber-50 text-amber-900 border-amber-200',       'icon' => 'M10 6.5v4.5M10 13.75h.01'],
        'error'   => ['bg' => 'bg-red-50 text-red-900 border-red-200',             'icon' => 'M10 6.5v4.5M10 13.75h.01'],
    ])->filter(fn ($style, $key) => session()->has($key));
@endphp

@if ($flashes->isNotEmpty())
    <div class="container-page pt-4">
        @foreach ($flashes as $key => $style)
            <div x-data="{ show: true }" x-show="show" x-transition.opacity
                 class="mb-2 flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm {{ $style['bg'] }}"
                 role="status">
                <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="none" stroke="currentColor"
                     stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                    <path d="{{ $style['icon'] }}"/>
                </svg>
                <p class="flex-1">{{ session($key) }}</p>
                <button type="button" @click="show = false" class="shrink-0 opacity-60 hover:opacity-100"
                        aria-label="{{ __('site.common.close') }}">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="m5 5 10 10M15 5 5 15" stroke-linecap="round"/>
                    </svg>
                </button>
            </div>
        @endforeach
    </div>
@endif
