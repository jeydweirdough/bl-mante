<x-app-layout>
    <x-slot name="title">Rooms</x-slot>

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Physical rooms</h1>
        <p class="mt-1 text-slate-600">Rooms are never deleted — they carry booking history. Take one off sale instead.</p>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-card title="Add a room">
            <form method="POST" action="{{ route('admin.rooms.store') }}" class="grid gap-3 sm:grid-cols-5">
                @csrf
                <div>
                    <x-input-label for="number" value="Number" />
                    <input type="text" id="number" name="number" required
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="room_type_id" value="Type" />
                    <select id="room_type_id" name="room_type_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        @foreach ($roomTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="floor" value="Floor" />
                    <input type="number" id="floor" name="floor" min="0" max="100"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div class="flex items-end">
                    <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Add</button>
                </div>
            </form>
        </x-card>

        <x-card bodyClass="p-0">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Room</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Type</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Floor</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Now</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700">Upcoming</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700">Save</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($rooms as $room)
                            <tr>
                                <form method="POST" action="{{ route('admin.rooms.update', $room) }}" id="room-{{ $room->id }}">
                                    @csrf
                                    @method('PUT')
                                </form>
                                <td class="px-4 py-2">
                                    <input type="text" name="number" form="room-{{ $room->id }}" value="{{ $room->number }}"
                                           class="w-24 rounded-md border-slate-300 text-sm font-mono shadow-sm">
                                </td>
                                <td class="px-4 py-2">
                                    <select name="room_type_id" form="room-{{ $room->id }}"
                                            class="rounded-md border-slate-300 text-sm shadow-sm">
                                        @foreach ($roomTypes as $type)
                                            <option value="{{ $type->id }}" @selected($room->room_type_id === $type->id)>{{ $type->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-4 py-2">
                                    <input type="number" name="floor" form="room-{{ $room->id }}" value="{{ $room->floor }}"
                                           class="w-20 rounded-md border-slate-300 text-sm shadow-sm">
                                </td>
                                <td class="px-4 py-2">
                                    <x-badge :classes="$room->status->badgeClasses()">{{ $room->status->label() }}</x-badge>
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $room->upcoming_count }}</td>
                                <td class="px-4 py-2">
                                    <div class="flex items-center justify-end gap-3">
                                        <label class="flex items-center gap-1.5 text-xs text-slate-600">
                                            <input type="checkbox" name="is_bookable" value="1" form="room-{{ $room->id }}"
                                                   @checked($room->is_bookable)
                                                   class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                            For sale
                                        </label>
                                        <button form="room-{{ $room->id }}"
                                                class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Save</button>
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
