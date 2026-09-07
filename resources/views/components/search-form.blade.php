@props([
    'packages',
    'roomTypes' => null,
    'defaults' => [],
    'adults' => 1,
    'children' => 0,
    'selectedRoomTypeId' => null,
    'action' => null,
])

{{--
    The booking search.

    Hick's Law drove the shape of this: it previously asked six questions at
    once (date, one of 24 hours, package, adults, children, room type). Now it
    asks two -- when, and how long -- because those are the only two most
    people have an opinion about.

    Guests and room type moved behind a disclosure with their defaults stated,
    so the common case is a decision-free path to a result. Nothing was
    removed; it is one click away and the summary line says what it is set to.
--}}

@php
    $partySize = (int) $adults + (int) $children;
    $selectedType = $roomTypes?->firstWhere('id', $selectedRoomTypeId);

    $summary = $partySize.' '.\Illuminate\Support\Str::plural('guest', $partySize)
        .($selectedType ? ', '.$selectedType->name : '');
@endphp

<form method="GET" action="{{ $action ?: route('availability') }}" class="space-y-5">
    <x-when-picker
        :date="$defaults['date'] ?? null"
        :hour="$defaults['hour'] ?? null" />

    <div>
        <span class="block text-sm font-medium text-slate-700">How long</span>

        {{-- Four packages as buttons rather than a dropdown. With this few
             options, showing them all costs one glance and removes the
             open-then-choose step entirely. --}}
        <div class="mt-1.5 grid grid-cols-2 gap-2 sm:grid-cols-4"
             x-data="{ chosen: {{ (int) ($defaults['duration_package_id'] ?? $packages->first()?->id) }} }">
            @foreach ($packages as $package)
                <label class="cursor-pointer">
                    <input type="radio" name="duration_package_id" value="{{ $package->id }}"
                           x-model.number="chosen" class="sr-only peer">
                    <span :class="chosen === {{ $package->id }}
                              ? 'border-slate-900 bg-slate-900 text-white'
                              : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'"
                          class="block rounded-md border px-3 py-2 text-center transition peer-focus-visible:ring-2 peer-focus-visible:ring-slate-500 peer-focus-visible:ring-offset-1">
                        <span class="block text-sm font-semibold">{{ $package->hours }} hours</span>
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <x-disclosure label="Guests and room type" :summary="$summary">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="adults" value="Adults" />
                <select id="adults" name="adults" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach (range(1, 6) as $n)
                        <option value="{{ $n }}" @selected((int) $adults === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <x-input-label for="children" value="Children" />
                <select id="children" name="children" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                    @foreach (range(0, 4) as $n)
                        <option value="{{ $n }}" @selected((int) $children === $n)>{{ $n }}</option>
                    @endforeach
                </select>
            </div>

            @if ($roomTypes && $roomTypes->count() > 1)
                <div class="sm:col-span-2">
                    <x-input-label for="room_type_id" value="Room type" />
                    <select id="room_type_id" name="room_type_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                        <option value="">Any room type</option>
                        @foreach ($roomTypes as $type)
                            <option value="{{ $type->id }}" @selected($selectedRoomTypeId === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
            @elseif ($selectedRoomTypeId)
                <input type="hidden" name="room_type_id" value="{{ $selectedRoomTypeId }}">
            @endif
        </div>
    </x-disclosure>

    {{-- One button. There is only one thing to do here. --}}
    <button type="submit"
            class="w-full inline-flex justify-center rounded-md bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-700">
        Search availability
    </button>
</form>
