<x-app-layout :title="$title" :description="$description" :canonical="$canonical" :noindex="false" :schema="$schema">

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Rooms and hourly rates</h1>
        <p class="mt-1 text-slate-600">
            Prices are for the whole package, not per hour. Availability below is live, and every stay is
            followed by a turnover break before the room is offered again.
        </p>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

        {{-- The window everything on this page is answered against. --}}
        <x-card>
            {{-- Same two questions as everywhere else. Guests sit behind the
                 disclosure because the overwhelming majority of searches are
                 for one or two people and the default already covers them. --}}
            <x-search-form
                :packages="$packages"
                :defaults="['date' => $startsAt->format('Y-m-d'), 'hour' => $startsAt->hour, 'duration_package_id' => $package?->id]"
                :adults="$adults"
                :children="$children"
                :action="route('rooms.index')" />

            <p class="mt-4 text-sm text-slate-600">
                Showing availability for
                <strong class="text-slate-900">{{ $startsAt->format('l j F') }}</strong>
                from <strong class="text-slate-900">{{ $startsAt->format('H:i') }}</strong>,
                {{ $package?->hours }} hours,
                for {{ $adults + $children }} {{ Str::plural('guest', $adults + $children) }}.
            </p>
        </x-card>

        {{-- One honest banner when the whole property is full for this window,
             so a visitor is not left scanning four cards to work it out. --}}
        @unless ($anyAvailable)
            <x-alert tone="warning">
                <p class="font-semibold">Nothing is free at {{ $startsAt->format('H:i') }} on {{ $startsAt->format('j F') }}.</p>
                <p class="mt-1">
                    Each room below shows the next time it comes free, within the next {{ $lookaheadDays }} days.
                    You can also <a href="{{ route('availability') }}" class="font-semibold underline">search another window</a>
                    or <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('hotel.contact_phone')) }}" class="font-semibold underline">call the desk</a>.
                </p>
            </x-alert>
        @endunless

        @foreach ($roomTypes as $roomType)
            @php($row = $rows->get($roomType->id))

            <article id="room-{{ $roomType->slug }}" class="scroll-mt-24 bg-white rounded-xl border border-slate-200 overflow-hidden lg:flex">
                <div class="lg:w-80 shrink-0">
                    @if ($photo = $roomType->coverPhoto())
                        <img src="{{ $photo->url() }}"
                             alt="{{ $photo->alt_text ?: $roomType->name.' at '.config('hotel.name') }}"
                             width="800" height="600" loading="lazy" decoding="async"
                             class="h-56 lg:h-full w-full object-cover">
                    @else
                        <div class="h-56 lg:h-full w-full bg-slate-100 flex items-center justify-center text-slate-400 text-sm">
                            Photo coming soon
                        </div>
                    @endif
                </div>

                <div class="p-6 flex-1 lg:flex lg:gap-8">
                    <div class="lg:flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900">
                                    <a href="{{ route('rooms.show', $roomType) }}" class="hover:underline">{{ $roomType->name }}</a>
                                </h2>
                                <p class="text-sm text-slate-500">
                                    Sleeps {{ $roomType->max_occupancy }}
                                    @if ($roomType->bed_configuration) &middot; {{ $roomType->bed_configuration }} @endif
                                    @if ($roomType->size_sqm) &middot; {{ $roomType->size_sqm }} m&sup2; @endif
                                </p>
                            </div>
                            <x-badge>{{ $roomType->bookable_rooms_count }} {{ Str::plural('room', $roomType->bookable_rooms_count) }} in total</x-badge>
                        </div>

                        <p class="mt-3 text-sm text-slate-600">{{ $roomType->short_description ?: $roomType->description }}</p>

                        @if ($roomType->amenities->isNotEmpty())
                            <ul class="mt-4 flex flex-wrap gap-2">
                                @foreach ($roomType->amenities->take(6) as $amenity)
                                    <li><x-badge>{{ $amenity->name }}</x-badge></li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="mt-5 grid gap-3 sm:grid-cols-4">
                            @foreach ($roomType->packagePrices->sortBy(fn ($p) => $p->durationPackage->hours) as $price)
                                <div class="rounded-lg border px-3 py-2 {{ $package?->id === $price->duration_package_id ? 'border-slate-900 bg-slate-50' : 'border-slate-200' }}">
                                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $price->durationPackage->hours }} hours</p>
                                    <p class="mt-1 font-semibold text-slate-900"><x-money :cents="$price->price_cents" /></p>
                                </div>
                            @endforeach
                        </div>

                        <a href="{{ route('rooms.show', $roomType) }}" class="mt-4 inline-block text-sm font-semibold text-slate-900 hover:underline">
                            Full details &rarr;
                        </a>
                    </div>

                    {{-- The availability verdict for this type. --}}
                    <div class="mt-6 lg:mt-0 lg:w-72 shrink-0">
                        <x-availability-panel
                            :row="$row"
                            :package="$package"
                            :room-type="$roomType"
                            :starts-at="$startsAt"
                            :adults="$adults"
                            :children="$children" />
                    </div>
                </div>
            </article>
        @endforeach

        <p class="text-sm text-slate-500">
            Availability updates as bookings come in. A room is only yours once the booking goes through &mdash;
            we check again at that moment and tell you straight away if someone got there first.
        </p>
    </div>
</x-app-layout>
