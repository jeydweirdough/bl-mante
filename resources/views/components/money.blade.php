@props(['cents' => 0])

{{-- Integer minor units in, a readable amount out. Never do arithmetic here. --}}
<span {{ $attributes->merge(['class' => 'tabular-nums']) }}>{{ \App\Support\Money::format((int) $cents) }}</span>
