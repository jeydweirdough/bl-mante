<x-app-layout>
    <x-slot name="title">Housekeeping</x-slot>

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Housekeeping</h1>
        <p class="mt-1 text-slate-600">
            These states describe each room right now. They do not decide whether a future window can be booked —
            that comes from the reservations themselves.
        </p>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <div class="grid gap-4 grid-cols-2 lg:grid-cols-5">
            @foreach ($statuses as $status)
                <div class="bg-white rounded-xl border border-slate-200 p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-500">{{ $status->label() }}</p>
                    <p class="mt-1 text-2xl font-bold text-slate-900">{{ ($grouped[$status->value] ?? collect())->count() }}</p>
                </div>
            @endforeach
        </div>

        <x-card bodyClass="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Room</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Right now</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Next booking</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rooms as $room)
                            @php($next = $room->reservations->first())
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono font-medium text-slate-900">{{ $room->number }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $room->roomType->name }}</td>
                                <td class="px-4 py-3">
                                    <x-badge :classes="$room->status->badgeClasses()">{{ $room->status->label() }}</x-badge>
                                    @unless ($room->is_bookable)
                                        <x-badge classes="bg-neutral-200 text-neutral-700 ring-neutral-500/20">Not for sale</x-badge>
                                    @endunless
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    @if ($next)
                                        <span class="font-mono text-xs">{{ $next->reference }}</span>
                                        <span class="text-slate-500">
                                            {{ $next->starts_at->format('j M, H:i') }}–{{ $next->ends_at->format('H:i') }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">Nothing upcoming</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if ($room->status === \App\Enums\RoomStatus::Cleaning)
                                            <form method="POST" action="{{ route('staff.rooms.cleaning-complete', $room) }}">
                                                @csrf
                                                <button class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Cleaning done</button>
                                            </form>
                                        @elseif ($room->status === \App\Enums\RoomStatus::OutOfService)
                                            @can('takeOutOfService', $room)
                                                <form method="POST" action="{{ route('staff.rooms.return-to-service', $room) }}">
                                                    @csrf
                                                    <button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Return to service</button>
                                                </form>
                                            @endcan
                                        @else
                                            <form method="POST" action="{{ route('staff.rooms.cleaning', $room) }}">
                                                @csrf
                                                <button class="rounded-md border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-50">Mark for cleaning</button>
                                            </form>
                                            @can('takeOutOfService', $room)
                                                <form method="POST" action="{{ route('staff.rooms.out-of-service', $room) }}">
                                                    @csrf
                                                    <button class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Out of service</button>
                                                </form>
                                            @endcan
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    </div>
</x-app-layout>
