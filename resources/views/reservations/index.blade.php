<x-app-layout>
    <x-slot name="title">My bookings</x-slot>

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">My bookings</h1>
                <p class="mt-1 text-slate-600">You can hold more than one booking on the same day, as long as the times do not overlap.</p>
            </div>
            <a href="{{ route('availability') }}" class="shrink-0 inline-flex rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Book a room</a>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        @if ($reservations->isEmpty())
            <x-card>
                <div class="text-center py-10">
                    <p class="font-medium text-slate-900">You have no bookings yet.</p>
                    <a href="{{ route('availability') }}" class="mt-3 inline-flex rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Find a room</a>
                </div>
            </x-card>
        @else
            @foreach (['Current and upcoming' => $upcoming, 'Past bookings' => $past] as $heading => $group)
                @if ($group->isNotEmpty())
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">{{ $heading }}</h2>
                        <div class="space-y-3">
                            @foreach ($group as $reservation)
                                <a href="{{ route('reservations.show', $reservation) }}"
                                   class="block bg-white rounded-xl border border-slate-200 p-5 hover:border-slate-400 transition">
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-semibold text-slate-900">{{ $reservation->roomType->name }}</h3>
                                                <x-badge :classes="$reservation->status->badgeClasses()">{{ $reservation->status->label() }}</x-badge>
                                            </div>
                                            <p class="mt-1 text-sm text-slate-600">
                                                {{ $reservation->starts_at->format('l j F Y') }},
                                                {{ $reservation->starts_at->format('H:i') }}–{{ $reservation->ends_at->format('H:i') }}
                                                &middot; {{ $reservation->package_hours }} hours
                                            </p>
                                            <p class="mt-0.5 text-sm text-slate-500">
                                                Booking {{ $reservation->reference }} &middot; Room {{ $reservation->room->number }}
                                            </p>
                                        </div>

                                        <div class="text-right">
                                            <p class="font-semibold text-slate-900"><x-money :cents="$reservation->total_cents" /></p>
                                            @if ($reservation->balance_due_cents > 0 && $reservation->status->occupiesRoom())
                                                <p class="text-xs text-amber-700 mt-0.5">
                                                    <x-money :cents="$reservation->balance_due_cents" /> still due
                                                </p>
                                            @elseif ($reservation->amount_refunded_cents > 0)
                                                <p class="text-xs text-slate-500 mt-0.5">
                                                    <x-money :cents="$reservation->amount_refunded_cents" /> refunded
                                                </p>
                                            @else
                                                <p class="text-xs text-emerald-700 mt-0.5">{{ $reservation->payment_status->label() }}</p>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($reservation->status === \App\Enums\ReservationStatus::Pending && $reservation->hold_expires_at)
                                        <p class="mt-3 text-xs text-amber-700">
                                            Held until {{ $reservation->hold_expires_at->format('H:i') }} — pay before then or the room is released.
                                        </p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach

            <div>{{ $reservations->links() }}</div>
        @endif
    </div>
</x-app-layout>
