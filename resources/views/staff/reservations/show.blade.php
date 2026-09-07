<x-app-layout>
    <x-slot name="title">{{ $reservation->reference }}</x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('staff.reservations.index') }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; All bookings</a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                    {{ $reservation->guestName() }}
                    <span class="font-mono text-lg text-slate-400">{{ $reservation->reference }}</span>
                </h1>
                <p class="mt-1 text-slate-600">
                    Room {{ $reservation->room->number }} ({{ $reservation->roomType->name }}) &middot;
                    {{ $reservation->starts_at->format('l j F, H:i') }}–{{ $reservation->ends_at->format('H:i') }} &middot;
                    held until {{ $reservation->blocked_until->format('H:i') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-badge :classes="$reservation->status->badgeClasses()">{{ $reservation->status->label() }}</x-badge>
                <x-badge :classes="$reservation->payment_status->badgeClasses()">{{ $reservation->payment_status->label() }}</x-badge>
                <x-badge>{{ $reservation->channel->label() }}</x-badge>
            </div>
        </div>

        <div class="mt-6 lg:grid lg:grid-cols-3 lg:gap-6">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Actions">
                    <div class="flex flex-wrap gap-2">
                        @can('checkIn', $reservation)
                            <form method="POST" action="{{ route('staff.reservations.check-in', $reservation) }}">
                                @csrf
                                <button class="rounded-md bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-700">Check in</button>
                            </form>
                        @endcan

                        @can('checkOut', $reservation)
                            <form method="POST" action="{{ route('staff.reservations.check-out', $reservation) }}">
                                @csrf
                                <button class="rounded-md border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50">Check out</button>
                            </form>
                        @endcan

                        @can('markNoShow', $reservation)
                            <form method="POST" action="{{ route('staff.reservations.no-show', $reservation) }}">
                                @csrf
                                <button class="rounded-md border border-rose-300 px-3.5 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Mark no-show</button>
                            </form>
                        @endcan

                        @can('requestExtension', $reservation)
                            <form method="POST" action="{{ route('staff.extensions.store', $reservation) }}" class="flex gap-1">
                                @csrf
                                <select name="additional_hours" class="rounded-md border-slate-300 text-sm shadow-sm">
                                    @foreach (range(1, 6) as $h)
                                        <option value="{{ $h }}">+{{ $h }}h</option>
                                    @endforeach
                                </select>
                                <button class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50">Extend</button>
                            </form>
                        @endcan
                    </div>

                    @if (! $reservation->status->occupiesRoom())
                        <p class="mt-3 text-sm text-slate-500">
                            This booking is {{ strtolower($reservation->status->label()) }} and no longer holds its room.
                        </p>
                    @endif
                </x-card>

                @can('recordPayment', $reservation)
                    <x-card title="Record a payment taken at the desk"
                            subtitle="Attributed to you, {{ auth()->user()->name }}, and kept on the record.">
                        <form method="POST" action="{{ route('staff.payments.store', $reservation) }}" class="grid gap-3 sm:grid-cols-4">
                            @csrf
                            <div>
                                <x-input-label for="amount" value="Amount" />
                                <input type="number" step="0.01" min="0.01" id="amount" name="amount"
                                       value="{{ number_format(max(0, $reservation->balance_due_cents) / 100, 2, '.', '') }}"
                                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                            </div>
                            <div>
                                <x-input-label for="method" value="Method" />
                                <select id="method" name="method" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                    @foreach (\App\Enums\PaymentMethod::faceToFace() as $method)
                                        <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="kind" value="For" />
                                <select id="kind" name="kind" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                    @foreach (\App\Enums\PaymentKind::cases() as $kind)
                                        <option value="{{ $kind->value }}" @selected($kind === \App\Enums\PaymentKind::Balance)>{{ $kind->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end">
                                <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Record</button>
                            </div>
                            <div class="sm:col-span-4">
                                <x-input-label for="notes" value="Note (optional)" />
                                <input type="text" id="notes" name="notes" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                            </div>
                        </form>
                    </x-card>
                @endcan

                @can('assignRoom', $reservation)
                    <x-card title="Move to another room" subtitle="Only rooms genuinely free for this exact window are listed.">
                        @if ($alternatives->isEmpty())
                            <p class="text-sm text-slate-500">No other room is free for this window.</p>
                        @else
                            <form method="POST" action="{{ route('staff.reservations.assign-room', $reservation) }}" class="grid gap-3 sm:grid-cols-3">
                                @csrf
                                <div>
                                    <x-input-label for="room_id" value="Room" />
                                    <select id="room_id" name="room_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                        @foreach ($alternatives as $room)
                                            <option value="{{ $room->id }}">{{ $room->number }} — {{ $room->roomType->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label for="reason" value="Reason" />
                                    <input type="text" id="reason" name="reason" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                </div>
                                <div class="flex items-end">
                                    <button class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50">Reassign</button>
                                </div>
                            </form>
                        @endif
                    </x-card>
                @endcan

                @can('cancel', $reservation)
                    <x-card title="Cancel this booking">
                        @if ($refundOutcome)
                            <p class="text-sm text-slate-600 mb-3">
                                Customer-initiated right now: <strong>{{ $refundOutcome->tier->label() }}</strong> —
                                <x-money :cents="$refundOutcome->refundableCents" /> returned.
                                A hotel-initiated cancellation refunds everything regardless of timing.
                            </p>
                        @endif

                        <form method="POST" action="{{ route('staff.reservations.cancel', $reservation) }}" class="grid gap-3 sm:grid-cols-3">
                            @csrf
                            <div>
                                <x-input-label for="initiator" value="Cancelled by" />
                                <select id="initiator" name="initiator" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                    <option value="customer">The customer</option>
                                    <option value="hotel">The hotel (full refund)</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label for="cancel_reason" value="Reason" />
                                <input type="text" id="cancel_reason" name="reason" required
                                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                            </div>
                            <div class="flex items-end">
                                <button class="w-full rounded-md border border-rose-300 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">Cancel booking</button>
                            </div>
                        </form>
                    </x-card>
                @endcan

                <x-card title="Timeline">
                    <ol class="space-y-3">
                        @foreach ($reservation->statusTransitions as $transition)
                            <li class="flex gap-3 text-sm">
                                <span class="mt-1.5 h-2 w-2 rounded-full bg-slate-300 shrink-0"></span>
                                <div>
                                    <p class="text-slate-900">{{ $transition->describe() }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $transition->created_at->format('j M Y, H:i:s') }} &middot;
                                        {{ $transition->actorLabel() }}
                                        @if ($transition->actor_role) ({{ $transition->actor_role->label() }}) @endif
                                    </p>
                                    @if ($transition->reason)
                                        <p class="text-xs text-slate-600 mt-0.5">{{ $transition->reason }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    @if ($reservation->assignments->count() > 1)
                        <h3 class="mt-6 text-sm font-semibold text-slate-900">Room moves</h3>
                        <ul class="mt-2 space-y-1 text-sm text-slate-600">
                            @foreach ($reservation->assignments as $assignment)
                                <li>
                                    {{ $assignment->isInitial()
                                        ? 'Assigned room '.$assignment->toRoom->number
                                        : 'Moved from '.$assignment->fromRoom->number.' to '.$assignment->toRoom->number }}
                                    <span class="text-xs text-slate-500">
                                        — {{ $assignment->created_at->format('j M, H:i') }},
                                        {{ $assignment->changedBy?->name ?? 'system' }}
                                        @if ($assignment->reason) ({{ $assignment->reason }}) @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>

            <div class="mt-6 lg:mt-0 space-y-4">
                <x-card title="Guest">
                    <dl class="space-y-2 text-sm">
                        <div><dt class="text-slate-500">Name</dt><dd class="text-slate-900">{{ $reservation->guestName() }}</dd></div>
                        <div><dt class="text-slate-500">Phone</dt><dd class="text-slate-900">{{ $reservation->guestPhone() ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Email</dt><dd class="text-slate-900 break-all">{{ $reservation->guestEmail() ?: '—' }}</dd></div>
                        <div>
                            <dt class="text-slate-500">Account</dt>
                            <dd class="text-slate-900">{{ $reservation->customer ? 'Registered customer' : 'No account (walk-in)' }}</dd>
                        </div>
                        <div><dt class="text-slate-500">Party</dt><dd class="text-slate-900">{{ $reservation->adults }} adults, {{ $reservation->children }} children</dd></div>
                        @if ($reservation->createdBy)
                            <div><dt class="text-slate-500">Booked by</dt><dd class="text-slate-900">{{ $reservation->createdBy->name }}</dd></div>
                        @endif
                    </dl>

                    @if ($reservation->customer_notes)
                        <p class="mt-3 rounded-md bg-slate-50 p-3 text-sm text-slate-700">{{ $reservation->customer_notes }}</p>
                    @endif
                </x-card>

                <x-card title="Money">
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-600">Package</dt><dd><x-money :cents="$reservation->package_price_cents" /></dd></div>
                        @if ($reservation->extras_total_cents)
                            <div class="flex justify-between"><dt class="text-slate-600">Extras</dt><dd><x-money :cents="$reservation->extras_total_cents" /></dd></div>
                        @endif
                        @if ($reservation->extensions_total_cents)
                            <div class="flex justify-between"><dt class="text-slate-600">Extensions</dt><dd><x-money :cents="$reservation->extensions_total_cents" /></dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-slate-600">Tax</dt><dd><x-money :cents="$reservation->tax_total_cents" /></dd></div>
                        <div class="flex justify-between border-t border-slate-200 pt-1.5 font-semibold"><dt>Total</dt><dd><x-money :cents="$reservation->total_cents" /></dd></div>
                        <div class="flex justify-between"><dt class="text-slate-600">Paid</dt><dd><x-money :cents="$reservation->amount_paid_cents" /></dd></div>
                        @if ($reservation->amount_refunded_cents)
                            <div class="flex justify-between"><dt class="text-slate-600">Refunded</dt><dd>-<x-money :cents="$reservation->amount_refunded_cents" /></dd></div>
                        @endif
                        <div class="flex justify-between font-semibold {{ $reservation->balance_due_cents > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                            <dt>Balance</dt><dd><x-money :cents="$reservation->balance_due_cents" /></dd>
                        </div>
                    </dl>
                </x-card>

                @if ($reservation->payments->isNotEmpty() || $reservation->refunds->isNotEmpty())
                    <x-card title="Ledger">
                        <ul class="divide-y divide-slate-100 text-sm">
                            @foreach ($reservation->payments as $payment)
                                <li class="py-2">
                                    <div class="flex justify-between gap-2">
                                        <span class="text-slate-900">{{ $payment->kind->label() }}</span>
                                        <span class="{{ $payment->isSettled() ? '' : 'text-slate-400 line-through' }}"><x-money :cents="$payment->amount_cents" /></span>
                                    </div>
                                    <p class="text-xs text-slate-500">
                                        {{ $payment->method->label() }} &middot; {{ $payment->status->label() }} &middot;
                                        taken by {{ $payment->attributionLabel() }}
                                        @if ($payment->paid_at) &middot; {{ $payment->paid_at->format('j M, H:i') }} @endif
                                    </p>
                                </li>
                            @endforeach

                            @foreach ($reservation->refunds as $refund)
                                <li class="py-2">
                                    <div class="flex justify-between gap-2">
                                        <span class="text-slate-900">Refund</span>
                                        <span class="text-emerald-700">-<x-money :cents="$refund->amount_cents" /></span>
                                    </div>
                                    <p class="text-xs text-slate-500">
                                        {{ $refund->reason->label() }}
                                        @if ($refund->tier_applied) &middot; {{ $refund->tier_applied->label() }} @endif
                                        &middot; {{ $refund->attributionLabel() }}
                                    </p>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif

                @can('addExtra', $reservation)
                    <x-card title="Add an extra">
                        <form method="POST" action="{{ route('staff.reservations.extras', $reservation) }}" class="space-y-2">
                            @csrf
                            <select name="extra_id" class="w-full rounded-md border-slate-300 text-sm shadow-sm">
                                @foreach ($extras as $extra)
                                    <option value="{{ $extra->id }}">{{ $extra->name }} — {{ \App\Support\Money::format($extra->price_cents) }}</option>
                                @endforeach
                            </select>
                            <div class="flex gap-2">
                                <input type="number" name="quantity" value="1" min="1" max="20" class="w-20 rounded-md border-slate-300 text-sm shadow-sm">
                                <button class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50">Add</button>
                            </div>
                        </form>
                    </x-card>
                @endcan

                @if ($reservation->internal_notes)
                    <x-card title="Internal notes">
                        <p class="text-sm text-slate-700 whitespace-pre-line">{{ $reservation->internal_notes }}</p>
                    </x-card>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
