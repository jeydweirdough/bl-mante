<x-app-layout>
    <x-slot name="title">Confirm your booking</x-slot>

    @php
        // Everything the running total needs, handed to Alpine as plain data.
        // The figures shown here are a courtesy: the authoritative price is
        // recalculated server-side by PricingService when the form is posted.
        $extrasData = $extras->map(fn ($e) => [
            'id' => $e->id,
            'name' => $e->name,
            'price' => $e->price_cents,
            'basis' => $e->pricing_basis->value,
        ])->values();
    @endphp

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
         x-data="bookingForm({
            packagePrice: {{ $quote->packagePriceCents }},
            taxBp: {{ $policy->tax_percent_bp }},
            serviceFee: {{ $policy->service_fee_cents }},
            downpaymentPercent: {{ $policy->downpayment_percent }},
            hours: {{ $window->hours }},
            adults: {{ $adults }},
            children: {{ $children }},
            extras: {{ Js::from($extrasData) }},
         })">

        <a href="{{ url()->previous() }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; Back</a>

        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">Confirm your booking</h1>

        @if ($stillFree === 0)
            <div class="mt-4">
                <x-alert tone="warning">
                    Every {{ $roomType->name }} was taken for that window while you were looking.
                    <a href="{{ route('availability') }}" class="font-semibold underline">Try another time</a>.
                </x-alert>
            </div>
        @elseif ($stillFree <= 2)
            <div class="mt-4">
                <x-alert tone="warning">
                    Only {{ $stillFree }} {{ Str::plural('room', $stillFree) }} of this type left for that window.
                    Availability is confirmed at the moment you book, not now.
                </x-alert>
            </div>
        @endif

        <form method="POST" action="{{ route('booking.store') }}" class="mt-6 lg:grid lg:grid-cols-3 lg:gap-8">
            @csrf
            <input type="hidden" name="room_type_id" value="{{ $roomType->id }}">
            <input type="hidden" name="duration_package_id" value="{{ $package->id }}">
            <input type="hidden" name="date" value="{{ $window->startsAt->format('Y-m-d') }}">
            <input type="hidden" name="hour" value="{{ $window->startsAt->hour }}">

            <div class="lg:col-span-2 space-y-6">
                <x-card title="Your stay">
                    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-slate-500">Room type</dt>
                            <dd class="font-medium text-slate-900">{{ $roomType->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Package</dt>
                            <dd class="font-medium text-slate-900">{{ $package->hours }} hours</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Arrive</dt>
                            <dd class="font-medium text-slate-900">{{ $window->startsAt->format('l j F, H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Leave by</dt>
                            <dd class="font-medium text-slate-900">{{ $window->endsAt->format('l j F, H:i') }}</dd>
                        </div>
                    </dl>

                    <p class="mt-4 text-xs text-slate-500">
                        The room is assigned when you book. You will see the room number on your confirmation.
                    </p>
                </x-card>

                <x-card title="Guests">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="adults" value="Adults" />
                            <select id="adults" name="adults" x-model.number="adults"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                @for ($n = 1; $n <= $roomType->max_occupancy; $n++)
                                    <option value="{{ $n }}" @selected($adults === $n)>{{ $n }}</option>
                                @endfor
                            </select>
                        </div>
                        <div>
                            <x-input-label for="children" value="Children" />
                            <select id="children" name="children" x-model.number="children"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                @for ($n = 0; $n <= max(0, $roomType->max_occupancy - 1); $n++)
                                    <option value="{{ $n }}" @selected($children === $n)>{{ $n }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-500">This room sleeps up to {{ $roomType->max_occupancy }}.</p>
                </x-card>

                @if ($extras->isNotEmpty())
                    <x-card title="Extras" subtitle="You can also add these after you book.">
                        <ul class="divide-y divide-slate-100">
                            @foreach ($extras as $extra)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $extra->name }}</p>
                                        <p class="text-xs text-slate-500">
                                            <x-money :cents="$extra->price_cents" /> {{ strtolower($extra->pricing_basis->label()) }}
                                            @if ($extra->description) &middot; {{ $extra->description }} @endif
                                        </p>
                                    </div>
                                    <select name="extras[{{ $extra->id }}]"
                                            x-model.number="quantities[{{ $extra->id }}]"
                                            class="w-20 rounded-md border-slate-300 shadow-sm text-sm">
                                        @foreach (range(0, 5) as $n)
                                            <option value="{{ $n }}">{{ $n }}</option>
                                        @endforeach
                                    </select>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif

                <x-card title="How would you like to pay?">
                    <div class="space-y-3">
                        @foreach ($paymentModes as $mode)
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 cursor-pointer hover:border-slate-400 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-50">
                                <input type="radio" name="payment_mode" value="{{ $mode->value }}"
                                       @checked($loop->first)
                                       class="mt-1 border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span>
                                    <span class="block font-medium text-slate-900">{{ $mode->label() }}</span>
                                    <span class="block text-sm text-slate-600">
                                        @if ($mode === \App\Enums\PaymentMode::Online)
                                            Pay in full, or {{ $policy->downpayment_percent }}% now and the rest at the property.
                                            Your booking is held for {{ $policy->unpaid_hold_minutes }} minutes while you pay.
                                        @else
                                            Your booking is confirmed straight away and you settle everything on arrival.
                                            Arrive within {{ $policy->no_show_grace_minutes }} minutes of your start time or the room is released.
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <x-input-label for="customer_notes" value="Anything we should know? (optional)" />
                        <textarea id="customer_notes" name="customer_notes" rows="2" maxlength="1000"
                                  class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">{{ old('customer_notes') }}</textarea>
                    </div>
                </x-card>

                <x-card title="Cancellation terms" subtitle="These are the terms your booking is made under and they will not change afterwards.">
                    <ul class="space-y-2 text-sm text-slate-700">
                        <li>More than {{ $policy->full_refund_hours_before }} hours before your start time — everything you paid is refunded.</li>
                        <li>Between {{ $policy->partial_refund_hours_before }} and {{ $policy->full_refund_hours_before }} hours before — the {{ $policy->downpayment_percent }}% downpayment is kept, anything above it is refunded.</li>
                        <li>Under {{ $policy->partial_refund_hours_before }} hours before — no refund.</li>
                        <li>Not arriving within {{ $policy->no_show_grace_minutes }} minutes of your start time counts as a no-show and forfeits payment.</li>
                        <li>One free move to another time is allowed if you ask more than {{ $policy->free_reschedule_hours_before }} hours ahead.</li>
                    </ul>

                    <label class="mt-4 flex items-start gap-2 text-sm">
                        <input type="checkbox" name="terms" value="1" required
                               class="mt-0.5 rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                        <span>I have read and accept these terms.</span>
                    </label>
                </x-card>
            </div>

            <div class="mt-8 lg:mt-0">
                <div class="lg:sticky lg:top-24 space-y-4">
                    <x-card title="Total">
                        <dl class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-slate-600">{{ $package->hours }}-hour package</dt>
                                <dd class="text-slate-900"><x-money :cents="$quote->packagePriceCents" /></dd>
                            </div>

                            <template x-if="extrasTotal > 0">
                                <div class="flex justify-between">
                                    <dt class="text-slate-600">Extras</dt>
                                    <dd class="text-slate-900" x-text="format(extrasTotal)"></dd>
                                </div>
                            </template>

                            <div class="flex justify-between">
                                <dt class="text-slate-600">Tax ({{ rtrim(rtrim(number_format($policy->taxPercent(), 2), '0'), '.') }}%)</dt>
                                <dd class="text-slate-900" x-text="format(tax)"></dd>
                            </div>

                            @if ($policy->service_fee_cents > 0)
                                <div class="flex justify-between">
                                    <dt class="text-slate-600">Service fee</dt>
                                    <dd class="text-slate-900"><x-money :cents="$policy->service_fee_cents" /></dd>
                                </div>
                            @endif

                            <div class="flex justify-between border-t border-slate-200 pt-2 mt-2 text-base font-semibold">
                                <dt class="text-slate-900">Total</dt>
                                <dd class="text-slate-900" x-text="format(total)"></dd>
                            </div>

                            <div class="flex justify-between text-slate-600">
                                <dt>Or pay {{ $policy->downpayment_percent }}% now</dt>
                                <dd x-text="format(downpayment)"></dd>
                            </div>
                        </dl>

                        <button type="submit"
                                @disabled($stillFree === 0)
                                class="mt-5 w-full inline-flex justify-center rounded-md bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            Confirm booking
                        </button>

                        <p class="mt-3 text-xs text-slate-500">
                            The room is checked again at this moment. If someone books it first you will be told, and nothing will be charged.
                        </p>
                    </x-card>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function bookingForm(config) {
            return {
                ...config,
                quantities: Object.fromEntries(config.extras.map(e => [e.id, 0])),

                get partySize() {
                    return Math.max(1, this.adults + this.children);
                },

                // Mirrors PricingService::assemble. Kept simple on purpose --
                // it is a preview, and the server recomputes the real figure.
                get extrasTotal() {
                    return this.extras.reduce((sum, extra) => {
                        const qty = this.quantities[extra.id] || 0;
                        if (qty === 0) return sum;

                        const multiplier = extra.basis === 'per_hour' ? this.hours
                            : extra.basis === 'per_person' ? this.partySize
                            : 1;

                        return sum + extra.price * qty * multiplier;
                    }, 0);
                },

                get taxable() {
                    return this.packagePrice + this.extrasTotal;
                },

                get tax() {
                    return Math.floor((this.taxable * this.taxBp + 5000) / 10000);
                },

                get total() {
                    return this.taxable + this.tax + this.serviceFee;
                },

                get downpayment() {
                    return Math.ceil(this.total * this.downpaymentPercent / 100);
                },

                format(cents) {
                    return '{{ config('hotel.currency_symbol') }}' +
                        (cents / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
