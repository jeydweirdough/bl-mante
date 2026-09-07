@props([
    'date' => null,
    'hour' => null,
    'presets' => true,
    'compact' => false,
])

@php
    use Carbon\CarbonImmutable;

    $selectedDate = $date ?: CarbonImmutable::now()->addHour()->format('Y-m-d');
    $selectedHour = $hour !== null ? (int) $hour : CarbonImmutable::now()->addHour()->hour;

    /*
     * Hick's Law: the time to choose grows with the number of options, so the
     * common intents are lifted out as single-tap presets. Most people picking
     * a time here want one of four things, and for them the decision collapses
     * from "one of 24 hours plus a date" to "one of four".
     *
     * The full picker stays for everyone else -- reducing choice must not mean
     * removing capability.
     */
    $now = CarbonImmutable::now();
    $nextHour = $now->addHour()->startOfHour();
    $tonight = $now->hour < 20 ? $now->setHour(20)->startOfHour() : $now->addDay()->setHour(20)->startOfHour();
    $tomorrowMorning = $now->addDay()->setHour(9)->startOfHour();
    $tomorrowAfternoon = $now->addDay()->setHour(14)->startOfHour();

    $quickOptions = [
        ['label' => 'Next hour', 'sub' => $nextHour->format('H:i'), 'at' => $nextHour],
        ['label' => 'Tonight', 'sub' => $tonight->format('D H:i'), 'at' => $tonight],
        ['label' => 'Tomorrow AM', 'sub' => $tomorrowMorning->format('D H:i'), 'at' => $tomorrowMorning],
        ['label' => 'Tomorrow PM', 'sub' => $tomorrowAfternoon->format('D H:i'), 'at' => $tomorrowAfternoon],
    ];

    /*
     * And when the full picker is used, the 24 hours are categorised. A flat
     * list of 24 is scanned linearly; four labelled groups of six let the eye
     * discard three quarters of them at a glance.
     */
    $hourGroups = [
        'Overnight' => range(0, 5),
        'Morning' => range(6, 11),
        'Afternoon' => range(12, 17),
        'Evening' => range(18, 23),
    ];
@endphp

<div x-data="{
        date: @js($selectedDate),
        hour: @js($selectedHour),
        choose(date, hour) { this.date = date; this.hour = hour; },
        isChosen(date, hour) { return this.date === date && this.hour === hour; },
     }"
     {{ $attributes->merge(['class' => 'space-y-3']) }}>

    @if ($presets)
        <div>
            <span class="block text-sm font-medium text-slate-700">When</span>
            <div class="mt-1.5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($quickOptions as $option)
                    <button type="button"
                            @click="choose(@js($option['at']->format('Y-m-d')), {{ $option['at']->hour }})"
                            :class="isChosen(@js($option['at']->format('Y-m-d')), {{ $option['at']->hour }})
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400'"
                            class="rounded-md border px-3 py-2 text-left transition">
                        <span class="block text-sm font-semibold">{{ $option['label'] }}</span>
                        <span class="block text-xs opacity-75">{{ $option['sub'] }}</span>
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-3 {{ $compact ? 'sm:grid-cols-2' : 'sm:grid-cols-2' }}">
        <div>
            <x-input-label for="date" :value="$presets ? 'Or pick a date' : 'Date'" />
            <input type="date" id="date" name="date" x-model="date"
                   min="{{ CarbonImmutable::today()->format('Y-m-d') }}"
                   max="{{ CarbonImmutable::today()->addDays(config('hotel.search_horizon_days'))->format('Y-m-d') }}"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500"
                   required>
        </div>

        <div>
            <x-input-label for="hour" value="Start time" />
            <select id="hour" name="hour" x-model.number="hour"
                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                @foreach ($hourGroups as $label => $hours)
                    <optgroup label="{{ $label }}">
                        @foreach ($hours as $h)
                            <option value="{{ $h }}">{{ sprintf('%02d:00', $h) }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
    </div>
</div>
