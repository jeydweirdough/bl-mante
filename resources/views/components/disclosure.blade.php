@props([
    'label' => 'More options',
    'summary' => null,
    'open' => false,
])

{{--
    Progressive disclosure.

    Hick's Law counts the options actually in front of someone, so an optional
    field that is collapsed costs nothing until it is wanted. Everything in
    here is genuinely optional -- anything required, or needed by most people,
    belongs on the surface where it can be seen.

    Native <details> rather than an Alpine panel: it works without JavaScript,
    is keyboard operable for free, and is announced correctly by screen
    readers without any ARIA of our own.
--}}

<details {{ $attributes->merge(['class' => 'group']) }} @if ($open) open @endif>
    <summary class="flex cursor-pointer list-none items-center gap-2 text-sm font-medium text-slate-700 hover:text-slate-900 marker:content-['']">
        <svg aria-hidden="true"
             class="h-4 w-4 shrink-0 text-slate-400 transition-transform group-open:rotate-90"
             viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M7.3 5.3a1 1 0 011.4 0l4 4a1 1 0 010 1.4l-4 4a1 1 0 01-1.4-1.4L10.6 10 7.3 6.7a1 1 0 010-1.4z" clip-rule="evenodd"/>
        </svg>
        <span>{{ $label }}</span>
        @if ($summary)
            <span class="text-slate-400 font-normal group-open:hidden">— {{ $summary }}</span>
        @endif
    </summary>

    <div class="mt-4">
        {{ $slot }}
    </div>
</details>
