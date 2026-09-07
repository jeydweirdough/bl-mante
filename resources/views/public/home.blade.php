{{--
    The public homepage.

    Structure matters as much as the words here. One H1 carrying the primary
    keyword and the location; H2 per major section; real prose between the
    photographs. The copy itself lives in config/content.php so it can be
    rewritten without touching this file, and every policy figure in it is
    interpolated from the live policy version.
--}}
<x-app-layout :schema="$schema" :description="$description" :canonical="$canonical" :noindex="false">

    {{-- ---------------------------------------------------------------- --}}
    {{-- Hero                                                             --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="relative bg-slate-900 overflow-hidden">
        <div aria-hidden="true" class="absolute inset-0 opacity-25">
            <div class="absolute -top-24 -right-24 h-96 w-96 rounded-full bg-sky-500 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/4 h-72 w-72 rounded-full bg-indigo-500 blur-3xl"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="max-w-3xl">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-sky-300">
                    {{ config('hotel.address.district') }}, {{ config('hotel.address.city') }}
                </p>

                <h1 class="mt-4 text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    {{ $c->get('hero.heading') }}
                </h1>

                <p class="mt-6 text-lg leading-8 text-slate-300">
                    {{ $c->get('hero.subheading') }}
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ route('availability') }}"
                       class="inline-flex items-center rounded-md bg-white px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                        {{ $c->get('hero.primary_cta') }}
                    </a>
                    <a href="{{ route('rooms.index') }}"
                       class="inline-flex items-center rounded-md border border-white/25 px-5 py-3 text-sm font-semibold text-white hover:bg-white/10">
                        {{ $c->get('hero.secondary_cta') }}
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- The booking widget sits over the fold, but below the H1: a visitor who
         already knows what they want goes straight here, and everyone else
         reads on. --}}
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-10 relative z-10">
        <x-card class="shadow-lg">
            <h2 class="text-sm font-semibold text-slate-900 mb-4">Check live availability</h2>
            <x-search-form :packages="$packages" />
        </x-card>
    </div>

    {{-- ---------------------------------------------------------------- --}}
    {{-- What this place is                                               --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
        <div class="lg:grid lg:grid-cols-3 lg:gap-12">
            <div class="lg:col-span-2">
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">
                    {{ $c->get('intro.heading') }}
                </h2>

                <div class="mt-6 space-y-5 text-lg leading-relaxed text-slate-700">
                    @foreach ($c->get('intro.paragraphs', []) as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>

            <aside class="mt-10 lg:mt-0">
                <div class="rounded-xl border border-slate-200 bg-white p-6">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">At a glance</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach ($c->get('good_to_know.items', []) as $item)
                            <div>
                                <dt class="font-medium text-slate-900">{{ $item['label'] }}</dt>
                                <dd class="text-slate-600">{{ $item['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </aside>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Differentiators                                                  --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">Why guests book here</h2>

            <div class="mt-10 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($c->get('highlights', []) as $highlight)
                    <div>
                        <h3 class="font-semibold text-slate-900">{{ $highlight['title'] }}</h3>
                        <p class="mt-2 text-slate-600 leading-relaxed">{{ $highlight['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- How it works                                                     --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <h2 class="text-3xl font-bold tracking-tight text-slate-900">
            {{ $c->get('how_it_works.heading') }}
        </h2>

        <ol class="mt-10 grid gap-8 md:grid-cols-3">
            @foreach ($c->get('how_it_works.steps', []) as $index => $step)
                <li class="relative pl-14">
                    <span aria-hidden="true"
                          class="absolute left-0 top-0 flex h-10 w-10 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white">
                        {{ $index + 1 }}
                    </span>
                    <h3 class="font-semibold text-slate-900">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-slate-600 leading-relaxed">{{ $step['body'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Rooms                                                            --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900">{{ $c->get('rooms.heading') }}</h2>
                    <p class="mt-2 text-slate-600">{{ $c->get('rooms.intro') }}</p>
                </div>
                <a href="{{ route('rooms.index') }}" class="text-sm font-semibold text-slate-900 hover:underline shrink-0">
                    Compare all room types &rarr;
                </a>
            </div>

            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($roomTypes as $roomType)
                    <article class="rounded-xl border border-slate-200 overflow-hidden flex flex-col bg-white">
                        @if ($photo = $roomType->coverPhoto())
                            <img src="{{ $photo->url() }}"
                                 alt="{{ $photo->alt_text ?: $roomType->name.' at '.config('hotel.name') }}"
                                 width="800" height="600" loading="lazy" decoding="async"
                                 class="h-44 w-full object-cover">
                        @else
                            <div class="h-44 w-full bg-slate-100 flex items-center justify-center text-slate-400 text-sm">
                                Photo coming soon
                            </div>
                        @endif

                        <div class="p-5 flex-1 flex flex-col">
                            <h3 class="font-semibold text-slate-900">
                                <a href="{{ route('rooms.show', $roomType) }}" class="hover:underline">{{ $roomType->name }}</a>
                            </h3>
                            <p class="mt-1 text-xs text-slate-500">
                                Sleeps {{ $roomType->max_occupancy }}@if ($roomType->bed_configuration) &middot; {{ $roomType->bed_configuration }}@endif
                            </p>
                            <p class="mt-2 text-sm text-slate-600 flex-1">{{ $roomType->short_description }}</p>

                            @if ($cheapest = $roomType->cheapestPriceCents())
                                <p class="mt-4 text-sm text-slate-500">
                                    from <span class="text-base font-semibold text-slate-900"><x-money :cents="$cheapest" /></span>
                                </p>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Amenities                                                        --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="max-w-2xl">
            <h2 class="text-3xl font-bold tracking-tight text-slate-900">{{ $c->get('amenities.heading') }}</h2>
            <p class="mt-2 text-slate-600">{{ $c->get('amenities.intro') }}</p>
        </div>

        <div class="mt-10 grid gap-10 md:grid-cols-3">
            @foreach ($c->get('amenities.groups', []) as $group)
                <div>
                    <h3 class="font-semibold text-slate-900">{{ $group['title'] }}</h3>
                    <ul class="mt-3 space-y-2">
                        @foreach ($group['items'] as $item)
                            <li class="flex items-start gap-2.5 text-slate-700">
                                <svg aria-hidden="true" class="mt-1 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/>
                                </svg>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Location                                                         --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="bg-white border-y border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:grid lg:grid-cols-2 lg:gap-12">
            <div>
                <h2 class="text-3xl font-bold tracking-tight text-slate-900">{{ $c->get('neighbourhood.heading') }}</h2>
                <p class="mt-4 text-lg leading-relaxed text-slate-700">{{ $c->get('neighbourhood.intro') }}</p>
                <p class="mt-4 text-slate-600 leading-relaxed">{{ $c->get('neighbourhood.getting_here') }}</p>

                {{-- The name, address and phone shown here must match the
                     Google Business Profile character for character. A
                     mismatch across the two is the most common reason a hotel
                     fails to rank locally. --}}
                <address class="mt-8 not-italic text-slate-700">
                    <p class="font-semibold text-slate-900">{{ config('hotel.name') }}</p>
                    <p>{{ config('hotel.address.street') }}</p>
                    <p>{{ config('hotel.address.district') }}, {{ config('hotel.address.city') }} {{ config('hotel.address.postal_code') }}</p>
                    <p>{{ config('hotel.address.country_name') }}</p>
                    <p class="mt-3">
                        <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('hotel.contact_phone')) }}"
                           class="font-medium text-slate-900 hover:underline">{{ config('hotel.contact_phone') }}</a>
                        <span class="text-slate-400">&middot;</span>
                        <a href="mailto:{{ config('hotel.contact_email') }}"
                           class="font-medium text-slate-900 hover:underline">{{ config('hotel.contact_email') }}</a>
                    </p>
                </address>
            </div>

            <div class="mt-10 lg:mt-0">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Nearby</h3>
                <ul class="mt-4 divide-y divide-slate-100">
                    @foreach ($c->get('neighbourhood.nearby', []) as $place)
                        <li class="py-3">
                            <p class="font-medium text-slate-900">{{ $place['name'] }}</p>
                            <p class="text-sm text-slate-600">{{ $place['detail'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- FAQ                                                              --}}
    {{-- ---------------------------------------------------------------- --}}
    <section id="faq" class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <h2 class="text-3xl font-bold tracking-tight text-slate-900">Frequently asked questions</h2>
        <p class="mt-2 text-slate-600">
            The cancellation and timing answers below are read from the terms currently in force,
            so they always match what the booking system will actually do.
        </p>

        {{-- Native <details> rather than a JavaScript accordion: the answers
             are in the HTML whether or not scripts run, which is what makes
             them readable by a crawler and usable without JS. --}}
        <div class="mt-8 divide-y divide-slate-200 border-y border-slate-200">
            @foreach ($faqs as $faq)
                <details class="group py-4" @if ($loop->first) open @endif>
                    <summary class="flex cursor-pointer items-start justify-between gap-4 font-medium text-slate-900 marker:content-['']">
                        <h3 class="text-base">{{ $faq['question'] }}</h3>
                        <svg aria-hidden="true"
                             class="mt-1 h-5 w-5 shrink-0 text-slate-400 transition-transform group-open:rotate-180"
                             viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5.3 7.3a1 1 0 011.4 0L10 10.6l3.3-3.3a1 1 0 111.4 1.4l-4 4a1 1 0 01-1.4 0l-4-4a1 1 0 010-1.4z" clip-rule="evenodd"/>
                        </svg>
                    </summary>
                    <p class="mt-3 pr-9 text-slate-700 leading-relaxed">{{ $faq['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- ---------------------------------------------------------------- --}}
    {{-- Closing call to action                                           --}}
    {{-- ---------------------------------------------------------------- --}}
    <section class="bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
            <h2 class="text-3xl font-bold tracking-tight text-white">{{ $c->get('closing_cta.heading') }}</h2>
            <p class="mt-3 text-slate-300">{{ $c->get('closing_cta.body') }}</p>
            <a href="{{ route('availability') }}"
               class="mt-8 inline-flex items-center rounded-md bg-white px-6 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-100">
                {{ $c->get('closing_cta.button') }}
            </a>
        </div>
    </section>
</x-app-layout>
