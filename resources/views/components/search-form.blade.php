@props([
    'packages',
    'roomTypes' => null,
    'defaults' => [],
    'adults' => 1,
    'children' => 0,
    'selectedRoomTypeId' => null,
])

{{-- The start hour is its own field rather than part of a datetime, because
     reservations start on the hour and nothing else is accepted. Making that
     structural in the form beats validating it out of free text. --}}
<form method="GET" action="{{ route('availability') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
    <div class="lg:col-span-2">
        <x-input-label for="date" value="Date" />
        <input type="date" id="date" name="date"
               value="{{ old('date', $defaults['date'] ?? now()->format('Y-m-d')) }}"
               min="{{ now()->format('Y-m-d') }}"
               max="{{ now()->addDays(config('hotel.search_horizon_days'))->format('Y-m-d') }}"
               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" required>
    </div>

    <div>
        <x-input-label for="hour" value="Start time" />
        <select id="hour" name="hour" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @foreach (range(0, 23) as $hour)
                <option value="{{ $hour }}" @selected((int) ($defaults['hour'] ?? 12) === $hour)>
                    {{ sprintf('%02d:00', $hour) }}
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="duration_package_id" value="Package" />
        <select id="duration_package_id" name="duration_package_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
            @foreach ($packages as $package)
                <option value="{{ $package->id }}" @selected(($defaults['duration_package_id'] ?? null) === $package->id)>
                    {{ $package->hours }} hours
                </option>
            @endforeach
        </select>
    </div>

    <div>
        <x-input-label for="adults" value="Guests" />
        <div class="mt-1 flex gap-2">
            <select id="adults" name="adults" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" aria-label="Adults">
                @foreach (range(1, 6) as $n)
                    <option value="{{ $n }}" @selected((int) $adults === $n)>{{ $n }} adult{{ $n === 1 ? '' : 's' }}</option>
                @endforeach
            </select>
            <select name="children" class="block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" aria-label="Children">
                @foreach (range(0, 4) as $n)
                    <option value="{{ $n }}" @selected((int) $children === $n)>{{ $n }} child{{ $n === 1 ? '' : 'ren' }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="flex items-end">
        <button type="submit" class="w-full inline-flex justify-center items-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700">
            Search
        </button>
    </div>

    @if ($roomTypes)
        <input type="hidden" name="room_type_id" value="{{ $selectedRoomTypeId }}">
    @endif
</form>
