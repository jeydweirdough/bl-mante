<x-app-layout :title="$title" :description="$description" :canonical="$canonical" :noindex="false" :schema="$schema">

    <section class="bg-slate-900">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16 lg:py-20">
            <nav aria-label="Breadcrumb" class="text-sm text-slate-400">
                <a href="{{ route('home') }}" class="hover:text-white">Home</a>
                <span class="mx-2" aria-hidden="true">/</span>
                <span class="text-slate-300">About</span>
            </nav>

            <h1 class="mt-4 text-4xl font-bold tracking-tight text-white sm:text-5xl">
                {{ $c->get('about.heading') }}
            </h1>
            <p class="mt-5 text-lg leading-8 text-slate-300">{{ $c->get('about.lead') }}</p>
        </div>
    </section>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-14 space-y-14">

        {{-- The narrative. Prose rather than bullet points: this is the page
             where a visitor decides whether the format is for them, and that
             is not a decision a feature list makes for them. --}}
        @foreach ($c->get('about.story', []) as $section)
            <section>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900">{{ $section['heading'] }}</h2>
                <div class="mt-4 space-y-4 text-lg leading-relaxed text-slate-700">
                    @foreach ($section['paragraphs'] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                </div>
            </section>
        @endforeach

        <section class="rounded-xl bg-white border border-slate-200 p-6 sm:p-8">
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">The property</h2>

            <dl class="mt-6 grid gap-6 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-slate-500">Rooms</dt>
                    <dd class="mt-1 text-3xl font-bold text-slate-900">{{ $roomCount }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Room types</dt>
                    <dd class="mt-1 text-3xl font-bold text-slate-900">{{ $roomTypeCount }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-slate-500">Reception</dt>
                    <dd class="mt-1 text-3xl font-bold text-slate-900">24h</dd>
                </div>
            </dl>

            <address class="mt-8 not-italic text-slate-700">
                <p class="font-semibold text-slate-900">{{ config('hotel.name') }}</p>
                <p>{{ config('hotel.address.street') }}</p>
                <p>{{ config('hotel.address.district') }}, {{ config('hotel.address.city') }} {{ config('hotel.address.postal_code') }}</p>
                <p>{{ config('hotel.address.country_name') }}</p>
            </address>
        </section>

        <section>
            <h2 class="text-2xl font-bold tracking-tight text-slate-900">How we work</h2>
            <div class="mt-6 grid gap-6 sm:grid-cols-2">
                @foreach ($c->get('about.values', []) as $value)
                    <div class="rounded-xl border border-slate-200 bg-white p-5">
                        <h3 class="font-semibold text-slate-900">{{ $value['title'] }}</h3>
                        <p class="mt-2 text-slate-600 leading-relaxed">{{ $value['body'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="border-t border-slate-200 pt-10">
            <p class="text-lg leading-relaxed text-slate-700">{{ $c->get('about.closing') }}</p>

            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('rooms.index') }}"
                   class="inline-flex rounded-md bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-700">
                    See the rooms
                </a>
                <a href="{{ route('contact') }}"
                   class="inline-flex rounded-md border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-900 hover:bg-slate-50">
                    Contact us
                </a>
            </div>
        </section>
    </div>
</x-app-layout>
