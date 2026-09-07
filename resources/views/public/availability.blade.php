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
            <form method="GET" action="{{ route('availability') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <x-input-label for="date" value="Date" />
                    <input type="date" id="date" name="date"
                           value="{{ $defaults['date'] ?? now()->format('Y-m-d') }}"
                           min="{{ now()->format('Y-m-d') }}"
                           max="{{ now()->addDays(config('hotel.search_horizon_days'))->format('Y-m-d') }}"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" required>
                </div>

                <div>
                    <x-input-label for="hour" value="Start time" />
                    <select id="hour" name="hour" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @foreach (range(0, 23) as $h)
                            <option value="{{ $h }}" @selected((int) ($defaults['hour'] ?? 12) === $h)>{{ sprintf('%02d:00', $h) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="duration_package_id" value="Package" />
                    <select id="duration_package_id" name="duration_package_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        @foreach ($packages as $p)
                            <option value="{{ $p->id }}" @selected(($defaults['duration_package_id'] ?? null) === $p->id)>{{ $p->hours }} hours</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <x-input-label for="room_type_id" value="Room type" />
                    <select id="room_type_id" name="room_type_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">Any</option>
                        @foreach ($roomTypes as $type)
                            <option value="{{ $type->id }}" @selected(($selectedRoomTypeId ?? null) === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-input-label for="adults" value="Guests" />
                        <div class="mt-1 flex gap-1">
                            <select id="adults" name="adults" class="block w-full rounded-md border-slate-300 shadow-sm text-sm" aria-label="Adults">
                                @foreach (range(1, 6) as $n)
                                    <option value="{{ $n }}" @selected((int) ($adults ?? 1) === $n)>{{ $n }}</option>
                                @endforeach
                            </select>
                            <select name="children" class="block w-full rounded-md border-slate-300 shadow-sm text-sm" aria-label="Children">
                                @foreach (range(0, 4) as $n)
                                    <option value="{{ $n }}" @selected((int) ($children ?? 0) === $n)>+{{ $n }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex items-end lg:col-span-6">
                    <button type="submit" class="inline-flex justify-center rounded-md bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Search</button>
                </div>
            </form>
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
