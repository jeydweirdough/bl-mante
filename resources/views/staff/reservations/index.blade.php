<x-app-layout>
    <x-slot name="title">Bookings</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Bookings</h1>
            <a href="{{ route('staff.reservations.create') }}"
               class="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">New booking</a>
        </div>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-card>
            <form method="GET" class="grid gap-3 sm:grid-cols-4">
                <div class="sm:col-span-2">
                    <x-input-label for="q" value="Search" />
                    <input type="search" id="q" name="q" value="{{ request('q') }}"
                           placeholder="Reference, name, phone or email"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                </div>
                <div>
                    <x-input-label for="status" value="Status" />
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        <option value="">Any</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="room_id" value="Room" />
                    <select id="room_id" name="room_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        <option value="">Any</option>
                        @foreach ($rooms as $room)
                            <option value="{{ $room->id }}" @selected((int) request('room_id') === $room->id)>{{ $room->number }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-4 flex gap-2">
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Filter</button>
                    <a href="{{ route('staff.reservations.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </x-card>

        <x-card bodyClass="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Reference</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Guest</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">When</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Room</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Status</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($reservations as $reservation)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('staff.reservations.show', $reservation) }}" class="font-mono font-medium text-slate-900 hover:underline">
                                        {{ $reservation->reference }}
                                    </a>
                                    <p class="text-xs text-slate-500">{{ $reservation->channel->label() }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <p class="text-slate-900">{{ $reservation->guestName() }}</p>
                                    <p class="text-xs text-slate-500">{{ $reservation->guestPhone() ?? $reservation->guestEmail() }}</p>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ $reservation->starts_at->format('j M') }}
                                    <span class="text-slate-500">{{ $reservation->starts_at->format('H:i') }}–{{ $reservation->ends_at->format('H:i') }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-slate-900">{{ $reservation->room->number }}</span>
                                    <p class="text-xs text-slate-500">{{ $reservation->roomType->name }}</p>
                                </td>
                                <td class="px-4 py-3">
                                    <x-badge :classes="$reservation->status->badgeClasses()">{{ $reservation->status->label() }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if ($reservation->balance_due_cents > 0)
                                        <span class="text-amber-700 font-medium"><x-money :cents="$reservation->balance_due_cents" /></span>
                                    @else
                                        <span class="text-slate-400">&mdash;</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-500">No bookings match that.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <div>{{ $reservations->links() }}</div>
    </div>
</x-app-layout>
