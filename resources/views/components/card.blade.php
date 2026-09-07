@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'bg-white rounded-xl border border-slate-200 shadow-sm']) }}>
    @if ($title || isset($actions))
        <header class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-4">
            <div>
                @if ($title)
                    <h2 class="text-base font-semibold text-slate-900">{{ $title }}</h2>
                @endif
                @if ($subtitle)
                    <p class="text-sm text-slate-500 mt-0.5">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="shrink-0">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div class="{{ $attributes->get('bodyClass', 'p-5') }}">
        {{ $slot }}
    </div>
</section>
