<x-app-layout>
    <x-slot name="title">Front desk</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Front desk</h1>
                <p class="mt-1 text-slate-600">{{ $day->format('l j F Y') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <form method="GET" class="flex items-center gap-2">
                    <input type="date" name="date" value="{{ $day->format('Y-m-d') }}"
                           class="rounded-md border-slate-300 text-sm shadow-sm" onchange="this.form.submit()">
                </form>
                <a href="{{ route('staff.reservations.create') }}"
                   class="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">
                    New booking
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        <div class="grid gap-4 grid-cols-2 lg:grid-cols-5">
            @foreach ([
                'Arrivals' => $stats['arrivals'],
                'In house' => $stats['in_house'],
                'Departures' => $stats['departures'],
                'Unpaid balances' => $stats['unpaid'],
                'Rooms cleaning' => $stats['cleaning'],
            ] as $label => $value)
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        @if ($pendingExtensions->isNotEmpty())
            <x-card title="Extension requests waiting" subtitle="Approving re-checks the room is still free for the extra hours.">
                <ul class="divide-y divide-slate-100">
                    @foreach ($pendingExtensions as $extension)
                        <li class="py-3 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-slate-900">
                                    Room {{ $extension->reservation->room->number }} —
                                    +{{ $extension->additional_hours }}h to {{ $extension->new_ends_at->format('H:i') }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ $extension->reservation->reference }} &middot;
                                    {{ $extension->reservation->guestName() }} &middot;
                                    <x-money :cents="$extension->charge_cents" />
                                </p>
                            </div>
                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('staff.extensions.approve', $extension) }}">
                                    @csrf
                                    <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('staff.extensions.refuse', $extension) }}" class="flex gap-1">
                                    @csrf
                                    <input type="text" name="reason" placeholder="Reason" required
                                           class="w-40 rounded-md border-slate-300 text-xs shadow-sm">
                                    <button class="rounded-md border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">Refuse</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ([
                ['Arrivals', $arrivals, 'starts_at'],
                ['In house', $inHouse, 'ends_at'],
                ['Departures', $departures, 'ends_at'],
            ] as [$heading, $group, $timeField])
                <x-card :title="$heading" :subtitle="$group->count().' today'">
                    @if ($group->isEmpty())
                        <p class="text-sm text-slate-500">Nothing here today.</p>
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($group as $reservation)
                                <li class="py-3">
                                    <a href="{{ route('staff.reservations.show', $reservation) }}" class="block hover:opacity-75">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-slate-900 truncate">{{ $reservation->guestName() }}</p>
                                                <p class="text-xs text-slate-500">
                                                    Room {{ $reservation->room->number }} &middot;
                                                    {{ $reservation->starts_at->format('H:i') }}–{{ $reservation->ends_at->format('H:i') }}
                                                </p>
                                            </div>
                                            <x-badge :classes="$reservation->status->badgeClasses()">{{ $reservation->status->label() }}</x-badge>
                                        </div>
                                    </a>

                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @can('checkIn', $reservation)
                                            <form method="POST" action="{{ route('staff.reservations.check-in', $reservation) }}">
                                                @csrf
                                                <button class="rounded-md bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white hover:bg-slate-700">Check in</button>
                                            </form>
                                        @endcan

                                        @can('checkOut', $reservation)
                                            <form method="POST" action="{{ route('staff.reservations.check-out', $reservation) }}">
                                                @csrf
                                                <button class="rounded-md border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-900 hover:bg-slate-50">Check out</button>
                                            </form>
                                        @endcan

                                        @can('markNoShow', $reservation)
                                            <form method="POST" action="{{ route('staff.reservations.no-show', $reservation) }}">
                                                @csrf
                                                <button class="rounded-md border border-rose-300 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">No-show</button>
                                            </form>
                                        @endcan

                                        @if ($reservation->balance_due_cents > 0)
                                            <x-badge classes="bg-amber-50 text-amber-800 ring-amber-600/20">
                                                <x-money :cents="$reservation->balance_due_cents" /> due
                                            </x-badge>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endforeach
        </div>

        {{-- The hourly board. Every reservation appears in each hour its
             blocked window covers, buffer included -- a room that is
             unavailable at 14:00 because the previous guest left at 13:30 is
             precisely what the desk needs to see. --}}
        <x-card title="By the hour" subtitle="Includes the turnover buffer after each stay." bodyClass="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($byHour as $slot)
                            <tr class="{{ $slot['reservations']->isEmpty() ? 'bg-white' : 'bg-slate-50/60' }}">
                                <th scope="row" class="w-20 px-5 py-2 text-left align-top font-mono text-xs text-slate-500">
                                    {{ sprintf('%02d:00', $slot['hour']) }}
                                </th>
                                <td class="px-5 py-2">
                                    @if ($slot['reservations']->isEmpty())
                                        <span class="text-xs text-slate-400">&mdash;</span>
                                    @else
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($slot['reservations'] as $reservation)
                                                @php
                                                    $inBuffer = $reservation->ends_at->lte($day->addHours($slot['hour']));
                                                @endphp
                                                <a href="{{ route('staff.reservations.show', $reservation) }}"
                                                   class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset
                                                        {{ $inBuffer
                                                            ? 'bg-slate-100 text-slate-500 ring-slate-300'
                                                            : $reservation->status->badgeClasses() }}">
                                                    <span class="font-mono">{{ $reservation->room->number }}</span>
                                                    <span>{{ Str::limit($reservation->guestName(), 16) }}</span>
                                                    @if ($inBuffer)
                                                        <span class="text-[10px] uppercase tracking-wide">turnover</span>
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>

        @if ($awaitingCleaning->isNotEmpty())
            <x-card title="Waiting on housekeeping" subtitle="These rooms cannot take a guest until they are cleared.">
                <div class="flex flex-wrap gap-2">
                    @foreach ($awaitingCleaning as $room)
                        <form method="POST" action="{{ route('staff.rooms.cleaning-complete', $room) }}">
                            @csrf
                            <button class="inline-flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">
                                Room {{ $room->number }} — mark clean
                            </button>
                        </form>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
