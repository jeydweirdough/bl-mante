<x-app-layout>
    <x-slot name="title">Cancel booking {{ $reservation->reference }}</x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('reservations.show', $reservation) }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; Back to booking</a>

        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Cancel this booking?</h1>

        <x-card class="mt-6">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Booking</dt>
                    <dd class="font-medium text-slate-900">{{ $reservation->reference }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Room</dt>
                    <dd class="font-medium text-slate-900">{{ $reservation->roomType->name }}, room {{ $reservation->room->number }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">When</dt>
                    <dd class="font-medium text-slate-900">
                        {{ $reservation->starts_at->format('l j F, H:i') }}–{{ $reservation->ends_at->format('H:i') }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Starts in</dt>
                    <dd class="font-medium text-slate-900">{{ $reservation->starts_at->diffForHumans(null, true) }}</dd>
                </div>
            </dl>
        </x-card>

        {{-- The figure and the reason are shown before anything is committed,
             and the same calculation is run again inside the transaction, so
             what is displayed here is what actually happens. --}}
        <x-card class="mt-4" title="What you get back">
            <p class="text-3xl font-bold text-slate-900"><x-money :cents="$outcome->refundableCents" /></p>
            <p class="mt-2 text-sm text-slate-600">{{ $outcome->explanation }}</p>

            @if ($outcome->forfeitedCents > 0)
                <p class="mt-3 text-sm text-amber-800">
                    <x-money :cents="$outcome->forfeitedCents" /> of the
                    <x-money :cents="$outcome->paidNetCents" /> you have paid is kept by the hotel.
                </p>
            @endif

            <p class="mt-4 text-xs text-slate-500">
                These are the terms your booking was made under, not today's.
            </p>
        </x-card>

        <form method="POST" action="{{ route('reservations.cancel', $reservation) }}" class="mt-6">
            @csrf
            @method('DELETE')

            <x-input-label for="reason" value="Reason (optional)" />
            <input type="text" id="reason" name="reason" maxlength="255"
                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">

            <div class="mt-6 flex gap-3">
                <button type="submit" class="rounded-md bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-500">
                    Yes, cancel this booking
                </button>
                <a href="{{ route('reservations.show', $reservation) }}"
                   class="rounded-md border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50">
                    Keep it
                </a>
            </div>
        </form>
    </div>
</x-app-layout>
