<x-app-layout>
    <x-slot name="title">Move booking {{ $reservation->reference }}</x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
         x-data="{
            date: '{{ $reservation->starts_at->format('Y-m-d') }}',
            hour: {{ $reservation->starts_at->hour }},
            checking: false,
            result: null,
            async check() {
                this.checking = true;
                this.result = null;
                try {
                    const url = new URL('{{ route('reservations.reschedule.check', $reservation) }}', window.location.origin);
                    url.searchParams.set('date', this.date);
                    url.searchParams.set('hour', this.hour);
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    this.result = await response.json();
                } catch (e) {
                    this.result = { available: false, message: 'Could not check that time just now.' };
                } finally {
                    this.checking = false;
                }
            }
         }">

        <a href="{{ route('reservations.show', $reservation) }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; Back to booking</a>

        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Move to another time</h1>
        <p class="mt-1 text-slate-600">
            Currently {{ $reservation->starts_at->format('l j F, H:i') }}–{{ $reservation->ends_at->format('H:i') }}.
            The stay stays {{ $reservation->package_hours }} hours long.
        </p>

        @if ($freeAvailable)
            <div class="mt-4">
                <x-alert tone="success">
                    This move is free. You get one per booking, while it is more than
                    {{ $reservation->policyVersion->free_reschedule_hours_before }} hours ahead.
                </x-alert>
            </div>
        @else
            <div class="mt-4">
                <x-alert tone="warning">
                    A free move is no longer possible on this booking — it needs to be more than
                    {{ $reservation->policyVersion->free_reschedule_hours_before }} hours ahead and unused.
                    You can cancel and rebook instead, which would return
                    <strong>{{ \App\Support\Money::format($outcomeIfCancelled->refundableCents) }}</strong>
                    ({{ strtolower($outcomeIfCancelled->tier->label()) }}) and price the new booking under today's rates.
                </x-alert>
            </div>
        @endif

        <x-card class="mt-6">
            <form method="POST" action="{{ route('reservations.reschedule', $reservation) }}">
                @csrf
                @method('PUT')

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="date" value="New date" />
                        <input type="date" id="date" name="date" x-model="date" @change="check()"
                               min="{{ now()->format('Y-m-d') }}"
                               max="{{ now()->addDays(config('hotel.search_horizon_days'))->format('Y-m-d') }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500" required>
                    </div>
                    <div>
                        <x-input-label for="hour" value="New start time" />
                        <select id="hour" name="hour" x-model.number="hour" @change="check()"
                                class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            @foreach (range(0, 23) as $h)
                                <option value="{{ $h }}">{{ sprintf('%02d:00', $h) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-4 min-h-[2.5rem]">
                    <template x-if="checking">
                        <p class="text-sm text-slate-500">Checking that time…</p>
                    </template>
                    <template x-if="result && result.available">
                        <p class="text-sm text-emerald-700" x-text="result.window + ' — that slot is free.'"></p>
                    </template>
                    <template x-if="result && !result.available">
                        <p class="text-sm text-rose-700" x-text="result.message"></p>
                    </template>
                </div>

                <button type="submit"
                        @disabled(! $freeAvailable)
                        class="mt-4 w-full inline-flex justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed">
                    Move my booking
                </button>

                <p class="mt-3 text-xs text-slate-500">
                    Availability is confirmed again when you press the button. If the slot goes in the meantime,
                    your existing booking is left exactly as it is.
                </p>
            </form>
        </x-card>
    </div>
</x-app-layout>
