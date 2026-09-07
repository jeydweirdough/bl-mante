<x-app-layout>
    <x-slot name="title">Booking {{ $reservation->reference }}</x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('reservations.index') }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; My bookings</a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $reservation->roomType->name }}</h1>
                <p class="mt-1 text-slate-600">
                    Booking {{ $reservation->reference }} &middot; Room {{ $reservation->room->number }}
                </p>
            </div>
            <div class="flex gap-2">
                <x-badge :classes="$reservation->status->badgeClasses()">{{ $reservation->status->label() }}</x-badge>
                <x-badge :classes="$reservation->payment_status->badgeClasses()">{{ $reservation->payment_status->label() }}</x-badge>
            </div>
        </div>

        @if ($reservation->status === \App\Enums\ReservationStatus::Pending && $reservation->hold_expires_at)
            <div class="mt-4">
                <x-alert tone="warning">
                    This booking is held until <strong>{{ $reservation->hold_expires_at->format('H:i') }}</strong>.
                    If no payment arrives by then the room is released automatically.
                </x-alert>
            </div>
        @endif

        <div class="mt-6 lg:grid lg:grid-cols-3 lg:gap-8">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Your stay">
                    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <dt class="text-slate-500">Arrive</dt>
                            <dd class="font-medium text-slate-900">{{ $reservation->starts_at->format('l j F Y, H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Leave by</dt>
                            <dd class="font-medium text-slate-900">{{ $reservation->ends_at->format('l j F Y, H:i') }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Package</dt>
                            <dd class="font-medium text-slate-900">{{ $reservation->package_hours }} hours</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Guests</dt>
                            <dd class="font-medium text-slate-900">
                                {{ $reservation->adults }} adult{{ $reservation->adults === 1 ? '' : 's' }}@if ($reservation->children), {{ $reservation->children }} child{{ $reservation->children === 1 ? '' : 'ren' }}@endif
                            </dd>
                        </div>
                    </dl>
                </x-card>

                <x-card title="What you owe">
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-slate-600">{{ $reservation->package_hours }}-hour package</dt>
                            <dd><x-money :cents="$reservation->package_price_cents" /></dd>
                        </div>

                        @foreach ($reservation->extras as $line)
                            <div class="flex justify-between">
                                <dt class="text-slate-600">{{ $line->name_snapshot }} <span class="text-slate-400">({{ $line->basisDescription() }})</span></dt>
                                <dd><x-money :cents="$line->line_total_cents" /></dd>
                            </div>
                        @endforeach

                        @if ($reservation->extensions_total_cents > 0)
                            <div class="flex justify-between">
                                <dt class="text-slate-600">Extra hours</dt>
                                <dd><x-money :cents="$reservation->extensions_total_cents" /></dd>
                            </div>
                        @endif

                        @if ($reservation->discount_total_cents > 0)
                            <div class="flex justify-between text-emerald-700">
                                <dt>Discount</dt>
                                <dd>-<x-money :cents="$reservation->discount_total_cents" /></dd>
                            </div>
                        @endif

                        <div class="flex justify-between">
                            <dt class="text-slate-600">Tax</dt>
                            <dd><x-money :cents="$reservation->tax_total_cents" /></dd>
                        </div>

                        @if ($reservation->fees_total_cents > 0)
                            <div class="flex justify-between">
                                <dt class="text-slate-600">Service fee</dt>
                                <dd><x-money :cents="$reservation->fees_total_cents" /></dd>
                            </div>
                        @endif

                        <div class="flex justify-between border-t border-slate-200 pt-2 font-semibold text-base">
                            <dt>Total</dt>
                            <dd><x-money :cents="$reservation->total_cents" /></dd>
                        </div>
                        <div class="flex justify-between text-slate-600">
                            <dt>Paid</dt>
                            <dd><x-money :cents="$reservation->amount_paid_cents" /></dd>
                        </div>
                        @if ($reservation->amount_refunded_cents > 0)
                            <div class="flex justify-between text-slate-600">
                                <dt>Refunded</dt>
                                <dd>-<x-money :cents="$reservation->amount_refunded_cents" /></dd>
                            </div>
                        @endif
                        <div class="flex justify-between font-semibold {{ $reservation->balance_due_cents > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                            <dt>{{ $reservation->balance_due_cents > 0 ? 'Still due' : 'Balance' }}</dt>
                            <dd><x-money :cents="$reservation->balance_due_cents" /></dd>
                        </div>
                    </dl>

                    @can('pay', $reservation)
                        <a href="{{ route('reservations.pay', $reservation) }}"
                           class="mt-5 inline-flex w-full justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                            Pay now
                        </a>
                    @endcan
                </x-card>

                @if ($reservation->extensions->isNotEmpty())
                    <x-card title="Extension requests">
                        <ul class="divide-y divide-slate-100">
                            @foreach ($reservation->extensions as $extension)
                                <li class="py-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-medium text-slate-900">
                                                +{{ $extension->additional_hours }} hour{{ $extension->additional_hours === 1 ? '' : 's' }}
                                                until {{ $extension->new_ends_at->format('H:i') }}
                                            </p>
                                            <p class="text-xs text-slate-500">
                                                Asked {{ $extension->requested_at->diffForHumans() }}
                                                @if ($extension->decided_at) &middot; answered {{ $extension->decided_at->diffForHumans() }} @endif
                                            </p>
                                            @if ($extension->refusal_reason)
                                                <p class="text-xs text-rose-700 mt-1">{{ $extension->refusal_reason }}</p>
                                            @endif
                                        </div>
                                        <div class="text-right shrink-0">
                                            <x-badge :classes="$extension->status->badgeClasses()">{{ $extension->status->label() }}</x-badge>
                                            <p class="text-xs text-slate-500 mt-1"><x-money :cents="$extension->charge_cents" /></p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif

                <x-card title="History">
                    <ol class="space-y-3">
                        @foreach ($reservation->statusTransitions as $transition)
                            <li class="flex gap-3 text-sm">
                                <span class="mt-1.5 h-2 w-2 rounded-full bg-slate-300 shrink-0"></span>
                                <div>
                                    <p class="text-slate-900">{{ $transition->describe() }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $transition->created_at->format('j M Y, H:i') }} &middot; {{ $transition->actorLabel() }}
                                    </p>
                                    @if ($transition->reason)
                                        <p class="text-xs text-slate-600 mt-0.5">{{ $transition->reason }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </x-card>
            </div>

            <div class="mt-8 lg:mt-0 space-y-4">
                @if ($reservation->isCancellable() || $reservation->isReschedulable() || $reservation->isExtendable())
                    <x-card title="Manage this booking">
                        <div class="space-y-3">
                            @can('reschedule', $reservation)
                                <a href="{{ route('reservations.reschedule.edit', $reservation) }}"
                                   class="block w-full text-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50">
                                    Move to another time
                                </a>
                                @unless ($freeRescheduleAvailable)
                                    <p class="text-xs text-slate-500">
                                        Your free move is no longer available — it needs to be more than
                                        {{ $reservation->policyVersion->free_reschedule_hours_before }} hours ahead and once per booking.
                                    </p>
                                @endunless
                            @endcan

                            @can('requestExtension', $reservation)
                                <form method="POST" action="{{ route('reservations.extensions.store', $reservation) }}" class="flex gap-2">
                                    @csrf
                                    <select name="additional_hours" class="flex-1 rounded-md border-slate-300 text-sm shadow-sm">
                                        @foreach (range(1, 6) as $h)
                                            <option value="{{ $h }}">+{{ $h }} hour{{ $h === 1 ? '' : 's' }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50">Ask</button>
                                </form>
                                <p class="text-xs text-slate-500">
                                    Extra hours are <x-money :cents="$reservation->roomType->extension_hourly_rate_cents" /> each,
                                    and only possible if the room is free afterwards.
                                </p>
                            @endcan

                            @can('cancel', $reservation)
                                <a href="{{ route('reservations.cancel.confirm', $reservation) }}"
                                   class="block w-full text-center rounded-md border border-rose-300 px-4 py-2.5 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                    Cancel booking
                                </a>
                                @if ($refundOutcome)
                                    <p class="text-xs text-slate-600">
                                        Cancelling now: <strong>{{ $refundOutcome->tier->label() }}</strong>
                                        @if ($refundOutcome->refundableCents > 0)
                                            — <x-money :cents="$refundOutcome->refundableCents" /> back.
                                        @endif
                                    </p>
                                @endif
                            @endcan
                        </div>
                    </x-card>
                @endif

                @if ($availableExtras->isNotEmpty())
                    <x-card title="Add an extra">
                        <form method="POST" action="{{ route('reservations.extras.store', $reservation) }}" class="space-y-3">
                            @csrf
                            <select name="extra_id" class="w-full rounded-md border-slate-300 text-sm shadow-sm">
                                @foreach ($availableExtras as $extra)
                                    <option value="{{ $extra->id }}">
                                        {{ $extra->name }} — {{ \App\Support\Money::format($extra->price_cents) }} {{ strtolower($extra->pricing_basis->label()) }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="flex gap-2">
                                <input type="number" name="quantity" value="1" min="1" max="20"
                                       class="w-20 rounded-md border-slate-300 text-sm shadow-sm">
                                <button type="submit" class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50">Add</button>
                            </div>
                        </form>
                    </x-card>
                @endif

                <x-card title="Your terms" subtitle="Frozen when you booked.">
                    <dl class="space-y-1.5 text-sm text-slate-600">
                        <div class="flex justify-between"><dt>Full refund before</dt><dd>{{ $reservation->policyVersion->full_refund_hours_before }}h</dd></div>
                        <div class="flex justify-between"><dt>Partial refund before</dt><dd>{{ $reservation->policyVersion->partial_refund_hours_before }}h</dd></div>
                        <div class="flex justify-between"><dt>Downpayment</dt><dd>{{ $reservation->policyVersion->downpayment_percent }}%</dd></div>
                        <div class="flex justify-between"><dt>No-show grace</dt><dd>{{ $reservation->policyVersion->no_show_grace_minutes }} min</dd></div>
                    </dl>
                </x-card>

                @if ($reservation->payments->isNotEmpty())
                    <x-card title="Payments">
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($reservation->payments as $payment)
                                <li class="py-2 flex justify-between gap-3">
                                    <div>
                                        <p class="text-slate-900">{{ $payment->kind->label() }}</p>
                                        <p class="text-xs text-slate-500">
                                            {{ $payment->method->label() }} &middot; {{ $payment->status->label() }}
                                            @if ($payment->paid_at) &middot; {{ $payment->paid_at->format('j M, H:i') }} @endif
                                        </p>
                                    </div>
                                    <p class="shrink-0 {{ $payment->isSettled() ? 'text-slate-900' : 'text-slate-400 line-through' }}">
                                        <x-money :cents="$payment->amount_cents" />
                                    </p>
                                </li>
                            @endforeach

                            @foreach ($reservation->refunds as $refund)
                                <li class="py-2 flex justify-between gap-3">
                                    <div>
                                        <p class="text-slate-900">Refund</p>
                                        <p class="text-xs text-slate-500">
                                            {{ $refund->tier_applied?->label() ?? $refund->reason->label() }} &middot; {{ $refund->status->label() }}
                                        </p>
                                    </div>
                                    <p class="shrink-0 text-emerald-700">-<x-money :cents="$refund->amount_cents" /></p>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
