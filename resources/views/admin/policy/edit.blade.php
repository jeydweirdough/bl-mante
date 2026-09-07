<x-app-layout>
    <x-slot name="title">Booking policy</x-slot>

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Booking policy</h1>
        <p class="mt-1 text-slate-600">
            Currently on version {{ $policy->version }}, which {{ $reservationsUnderCurrent }}
            {{ Str::plural('booking', $reservationsUnderCurrent) }} were made under.
        </p>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-alert tone="info">
            Saving publishes a <strong>new version</strong> rather than editing this one.
            Every booking already made keeps the terms it was made under — changing the cancellation
            window today cannot alter what a guest who booked yesterday is owed.
        </x-alert>

        <form method="POST" action="{{ route('admin.policy.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <x-card title="Availability">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="turnover_buffer_minutes" value="Turnover buffer (minutes)" />
                        <input type="number" id="turnover_buffer_minutes" name="turnover_buffer_minutes" min="0" max="720"
                               value="{{ old('turnover_buffer_minutes', $policy->turnover_buffer_minutes) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        <p class="mt-1 text-xs text-slate-500">
                            How long a room is unbookable after each stay. Existing bookings keep the buffer they were made with.
                        </p>
                    </div>
                    <div>
                        <x-input-label for="unpaid_hold_minutes" value="Unpaid hold (minutes)" />
                        <input type="number" id="unpaid_hold_minutes" name="unpaid_hold_minutes" min="5" max="1440"
                               value="{{ old('unpaid_hold_minutes', $policy->unpaid_hold_minutes) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        <p class="mt-1 text-xs text-slate-500">
                            How long an online booking is held before payment. Pay-at-property bookings are confirmed
                            immediately and are not affected.
                        </p>
                    </div>
                    <div>
                        <x-input-label for="no_show_grace_minutes" value="No-show grace (minutes)" />
                        <input type="number" id="no_show_grace_minutes" name="no_show_grace_minutes" min="0" max="720"
                               value="{{ old('no_show_grace_minutes', $policy->no_show_grace_minutes) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        <p class="mt-1 text-xs text-slate-500">How long after the start time before a guest is marked a no-show.</p>
                    </div>
                    <div>
                        <x-input-label for="free_reschedule_hours_before" value="Free reschedule window (hours)" />
                        <input type="number" id="free_reschedule_hours_before" name="free_reschedule_hours_before" min="0" max="720"
                               value="{{ old('free_reschedule_hours_before', $policy->free_reschedule_hours_before) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        <p class="mt-1 text-xs text-slate-500">One free move per booking, if asked this far ahead.</p>
                    </div>
                </div>
            </x-card>

            <x-card title="Payment and cancellation">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="downpayment_percent" value="Downpayment (%)" />
                        <input type="number" id="downpayment_percent" name="downpayment_percent" min="1" max="100"
                               value="{{ old('downpayment_percent', $policy->downpayment_percent) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div>
                        <x-input-label for="full_refund_hours_before" value="Full refund before (hours)" />
                        <input type="number" id="full_refund_hours_before" name="full_refund_hours_before" min="1" max="720"
                               value="{{ old('full_refund_hours_before', $policy->full_refund_hours_before) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div>
                        <x-input-label for="partial_refund_hours_before" value="Partial refund before (hours)" />
                        <input type="number" id="partial_refund_hours_before" name="partial_refund_hours_before" min="0" max="719"
                               value="{{ old('partial_refund_hours_before', $policy->partial_refund_hours_before) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                </div>

                <p class="mt-3 text-sm text-slate-600">
                    Cancelling more than {{ $policy->full_refund_hours_before }} hours out returns everything.
                    Between {{ $policy->partial_refund_hours_before }} and {{ $policy->full_refund_hours_before }} hours,
                    the {{ $policy->downpayment_percent }}% downpayment is kept. Under
                    {{ $policy->partial_refund_hours_before }} hours, nothing is returned.
                </p>
            </x-card>

            <x-card title="Tax and fees">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="tax_percent" value="Tax (%)" />
                        <input type="number" step="0.01" id="tax_percent" name="tax_percent" min="0" max="100"
                               value="{{ old('tax_percent', $policy->taxPercent()) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div>
                        <x-input-label for="service_fee" value="Service fee ({{ config('hotel.currency_symbol') }})" />
                        <input type="number" step="0.01" id="service_fee" name="service_fee" min="0"
                               value="{{ old('service_fee', number_format($policy->service_fee_cents / 100, 2, '.', '')) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                </div>
            </x-card>

            <x-card>
                <x-input-label for="change_note" value="What changed and why (optional)" />
                <input type="text" id="change_note" name="change_note" maxlength="255"
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">

                <button type="submit" class="mt-4 rounded-md bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                    Publish new version
                </button>
            </x-card>
        </form>

        <x-card title="Version history" bodyClass="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Version</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Published</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">By</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Buffer</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Hold</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Down</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Refund tiers</th>
                            <th class="px-4 py-2 text-left font-semibold text-slate-700">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($history as $version)
                            <tr class="{{ $version->is_current ? 'bg-emerald-50/60' : '' }}">
                                <td class="px-4 py-2 font-medium text-slate-900">
                                    v{{ $version->version }}
                                    @if ($version->is_current)
                                        <x-badge classes="bg-emerald-100 text-emerald-800 ring-emerald-600/20">current</x-badge>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $version->effective_from->format('j M Y, H:i') }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $version->createdBy?->name ?? 'System' }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ $version->turnover_buffer_minutes }}m</td>
                                <td class="px-4 py-2 text-slate-600">{{ $version->unpaid_hold_minutes }}m</td>
                                <td class="px-4 py-2 text-slate-600">{{ $version->downpayment_percent }}%</td>
                                <td class="px-4 py-2 text-slate-600">{{ $version->full_refund_hours_before }}h / {{ $version->partial_refund_hours_before }}h</td>
                                <td class="px-4 py-2 text-slate-500">{{ $version->change_note }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-app-layout>
