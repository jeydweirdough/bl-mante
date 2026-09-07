{{-- Result pages are noindex with a canonical back to the bare search:
     one URL per date/hour/package is effectively unlimited near-duplicate
     pages whose content is stale within the hour. --}}
<x-app-layout :title="$title" :description="$description ?? null" :canonical="$canonical" :noindex="$noindex">

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">What is free</h1>
        <p class="mt-1 text-slate-600">Pick a date, a start hour and a package. Results are live, but a room is only yours once the booking goes through.</p>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <x-card>
            {{-- Two visible questions -- when, and how long -- with guests and
                 room type behind a disclosure. Hick's Law: the fewer options
                 competing for attention, the faster the decision, and most
                 visitors have no opinion about the ones now hidden. --}}
            <x-search-form
                :packages="$packages"
                :room-types="$roomTypes"
                :defaults="$defaults"
                :adults="$adults ?? 1"
                :children="$children ?? 0"
                :selected-room-type-id="$selectedRoomTypeId ?? null" />
        </x-card>

        @if ($results !== null)
            <div>
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ $window->startsAt->format('l j F') }},
                        {{ $window->startsAt->format('H:i') }}–{{ $window->endsAt->format('H:i') }}
                    </h2>
                    <p class="text-sm text-slate-500">
                        The room is held until {{ $window->blockedUntil->format('H:i') }} to allow
                        {{ $window->bufferMinutes }} minutes for turnover.
                    </p>
                </div>

                @if ($results->isEmpty())
                    <x-card>
                        <div class="text-center py-8">
                            <p class="font-medium text-slate-900">Nothing is free for that window.</p>
                            <p class="mt-1 text-sm text-slate-600">
                                Try a different start hour or a shorter package. A room booked until
                                {{ $window->startsAt->format('H:i') }} still needs its turnover break before the next guest.
                            </p>
                        </div>
                    </x-card>
                @else
                    <div class="grid gap-6 lg:grid-cols-2">
                        @foreach ($results as $result)
                            @php($type = $result['room_type'])
                            <x-card>
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="font-semibold text-slate-900">{{ $type->name }}</h3>
                                        <p class="text-sm text-slate-500">Sleeps up to {{ $type->max_occupancy }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        @if ($result['price_cents'] !== null)
                                            <p class="text-lg font-semibold text-slate-900"><x-money :cents="$result['price_cents']" /></p>
                                            <p class="text-xs text-slate-500">for {{ $package->hours }} hours</p>
                                        @else
                                            <p class="text-sm text-amber-700">No price set</p>
                                        @endif
                                    </div>
                                </div>

                                <p class="mt-3 text-sm text-emerald-700">
                                    {{ $result['rooms']->count() }} room{{ $result['rooms']->count() === 1 ? '' : 's' }} free
                                </p>

                                <ul class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($result['rooms']->take(12) as $room)
                                        <li><x-badge classes="bg-emerald-50 text-emerald-800 ring-emerald-600/20">{{ $room->number }}</x-badge></li>
                                    @endforeach
                                    @if ($result['rooms']->count() > 12)
                                        <li><x-badge>+{{ $result['rooms']->count() - 12 }} more</x-badge></li>
                                    @endif
                                </ul>

                                @if ($result['price_cents'] !== null)
                                    <a href="{{ route('booking.create', [
                                            'room_type_id' => $type->id,
                                            'duration_package_id' => $package->id,
                                            'date' => $window->startsAt->format('Y-m-d'),
                                            'hour' => $window->startsAt->hour,
                                            'adults' => $adults ?? 1,
                                            'children' => $children ?? 0,
                                       ]) }}"
                                       class="mt-5 inline-flex w-full justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                                        @auth Book this @else Sign in to book @endauth
                                    </a>
                                @endif
                            </x-card>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-app-layout>
