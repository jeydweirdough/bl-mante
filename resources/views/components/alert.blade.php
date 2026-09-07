@props(['tone' => 'info'])

@php
    $classes = match ($tone) {
        'success' => 'bg-emerald-50 text-emerald-900 ring-emerald-600/20',
        'warning' => 'bg-amber-50 text-amber-900 ring-amber-600/20',
        'error' => 'bg-rose-50 text-rose-900 ring-rose-600/20',
        default => 'bg-sky-50 text-sky-900 ring-sky-600/20',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg px-4 py-3 text-sm ring-1 ring-inset $classes"]) }} role="status">
    {{ $slot }}
</div>
