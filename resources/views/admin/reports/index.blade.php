<x-app-layout>
    <x-slot name="title">Reports</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Reports</h1>
                <p class="mt-1 text-slate-600">{{ $from->format('j M Y') }} to {{ $to->format('j M Y') }}</p>
            </div>
            <form method="GET" class="flex items-end gap-2">
                <div>
                    <x-input-label for="from" value="From" />
                    <input type="date" id="from" name="from" value="{{ $from->format('Y-m-d') }}"
                           class="mt-1 rounded-md border-slate-300 text-sm shadow-sm">
                </div>
                <div>
                    <x-input-label for="to" value="To" />
                    <input type="date" id="to" name="to" value="{{ $to->format('Y-m-d') }}"
                           class="mt-1 rounded-md border-slate-300 text-sm shadow-sm">
                </div>
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Apply</button>
            </form>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Occupancy</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $occupancy['rate'] }}%</p>
                <p class="mt-1 text-xs text-slate-500">
                    {{ number_format($occupancy['sold_hours']) }} of {{ number_format($occupancy['capacity_hours']) }} room-hours
                </p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Collected</p>
                <p class="mt-1 text-3xl font-bold text-slate-900"><x-money :cents="$revenue['gross_cents']" /></p>
                <p class="mt-1 text-xs text-slate-500">Settled payments in the period</p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Refunded</p>
                <p class="mt-1 text-3xl font-bold text-slate-900"><x-money :cents="$revenue['refunded_cents']" /></p>
                <p class="mt-1 text-xs text-slate-500">Net <x-money :cents="$revenue['net_cents']" /></p>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-5">
                <p class="text-xs uppercase tracking-wide text-slate-500">Outstanding</p>
                <p class="mt-1 text-3xl font-bold text-amber-700"><x-money :cents="$revenue['outstanding_cents']" /></p>
                <p class="mt-1 text-xs text-slate-500">Owed on live bookings</p>
            </div>
        </div>

        <x-alert tone="info">
            Occupancy is measured in room-hours, not room-nights, because rooms here are sold by the hour.
            The {{ number_format($occupancy['buffer_hours']) }} hours of turnover buffer are excluded from what counts as sold —
            that time is unsellable, not revenue.
        </x-alert>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-card title="Money by channel">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($byChannel as $label => $row)
                            <tr>
                                <td class="py-2 text-slate-700">{{ $label }}</td>
                                <td class="py-2 text-right text-slate-500">{{ $row['count'] }}</td>
                                <td class="py-2 text-right font-medium text-slate-900"><x-money :cents="$row['total_cents']" /></td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-slate-500">No payments in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>

            <x-card title="Money by method">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($byMethod as $label => $row)
                            <tr>
                                <td class="py-2 text-slate-700">{{ $label }}</td>
                                <td class="py-2 text-right text-slate-500">{{ $row['count'] }}</td>
                                <td class="py-2 text-right font-medium text-slate-900"><x-money :cents="$row['total_cents']" /></td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-slate-500">No payments in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>

            <x-card title="Face-to-face money by staff member"
                    subtitle="Every payment taken at the desk is attributed to the person who took it.">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($byStaff as $row)
                            <tr>
                                <td class="py-2 text-slate-700">{{ $row['name'] }}</td>
                                <td class="py-2 text-right text-slate-500">{{ $row['count'] }} payments</td>
                                <td class="py-2 text-right font-medium text-slate-900"><x-money :cents="$row['total_cents']" /></td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-slate-500">Nothing recorded at the desk in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>

            <x-card title="Bookings by status">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($byStatus as $label => $count)
                            <tr>
                                <td class="py-2 text-slate-700">{{ $label }}</td>
                                <td class="py-2 text-right font-medium text-slate-900">{{ $count }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-slate-500">No bookings starting in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>

        <x-card title="By room type" bodyClass="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Room type</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700">Bookings</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700">Hours sold</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700">Booking value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($byRoomType as $row)
                            <tr>
                                <td class="px-4 py-2 text-slate-900">{{ $row['name'] }}</td>
                                <td class="px-4 py-2 text-right text-slate-700">{{ $row['count'] }}</td>
                                <td class="px-4 py-2 text-right text-slate-700">{{ number_format($row['hours']) }}</td>
                                <td class="px-4 py-2 text-right font-medium text-slate-900"><x-money :cents="$row['revenue_cents']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Nothing sold in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card title="Recent administrative actions" subtitle="Status changes have their own trail on each booking.">
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($recentAudit as $log)
                    <li class="py-2 flex flex-wrap justify-between gap-2">
                        <div>
                            <span class="font-mono text-xs text-slate-500">{{ $log->action }}</span>
                            <span class="text-slate-700">by {{ $log->actorLabel() }}</span>
                        </div>
                        <span class="text-xs text-slate-500">{{ $log->created_at->format('j M Y, H:i') }}</span>
                    </li>
                @empty
                    <li class="py-3 text-slate-500">Nothing recorded yet.</li>
                @endforelse
            </ul>
        </x-card>
    </div>
</x-app-layout>
