<x-app-layout>
    <x-slot name="title">Pay for booking {{ $reservation->reference }}</x-slot>

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('reservations.show', $reservation) }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; Back to booking</a>

        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">How much would you like to pay?</h1>
        <p class="mt-1 text-slate-600">
            You will be taken to our payment provider. Your card details never reach us.
        </p>

        @if ($reservation->status === \App\Enums\ReservationStatus::Pending && $reservation->hold_expires_at)
            <div class="mt-4">
                <x-alert tone="warning">
                    Your room is held until <strong>{{ $reservation->hold_expires_at->format('H:i') }}</strong>.
                </x-alert>
            </div>
        @endif

        <form method="POST" action="{{ route('reservations.pay.checkout', $reservation) }}" class="mt-6 space-y-4">
            @csrf

            <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-5 cursor-pointer hover:border-slate-400 has-[:checked]:border-slate-900 has-[:checked]:ring-1 has-[:checked]:ring-slate-900">
                <input type="radio" name="portion" value="full" checked
                       class="mt-1 border-slate-300 text-slate-900 focus:ring-slate-500">
                <span class="flex-1">
                    <span class="flex items-baseline justify-between">
                        <span class="font-semibold text-slate-900">Pay in full</span>
                        <span class="text-lg font-semibold text-slate-900"><x-money :cents="$fullAmount" /></span>
                    </span>
                    <span class="mt-1 block text-sm text-slate-600">Nothing left to settle when you arrive.</span>
                </span>
            </label>

            @if ($downpaymentAmount < $fullAmount && $downpaymentAmount > 0)
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white p-5 cursor-pointer hover:border-slate-400 has-[:checked]:border-slate-900 has-[:checked]:ring-1 has-[:checked]:ring-slate-900">
                    <input type="radio" name="portion" value="downpayment"
                           class="mt-1 border-slate-300 text-slate-900 focus:ring-slate-500">
                    <span class="flex-1">
                        <span class="flex items-baseline justify-between">
                            <span class="font-semibold text-slate-900">Pay {{ $reservation->policyVersion->downpayment_percent }}% now</span>
                            <span class="text-lg font-semibold text-slate-900"><x-money :cents="$downpaymentAmount" /></span>
                        </span>
                        <span class="mt-1 block text-sm text-slate-600">
                            <x-money :cents="$fullAmount - $downpaymentAmount" /> due when you arrive.
                        </span>
                        <span class="mt-1 block text-xs text-amber-700">
                            If you cancel between {{ $reservation->policyVersion->partial_refund_hours_before }} and
                            {{ $reservation->policyVersion->full_refund_hours_before }} hours before your stay, this amount is not returned.
                        </span>
                    </span>
                </label>
            @endif

            <button type="submit" class="w-full inline-flex justify-center rounded-md bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700">
                Continue to payment
            </button>
        </form>
    </div>
</x-app-layout>
