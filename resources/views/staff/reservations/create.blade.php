<x-app-layout>
    <x-slot name="title">New booking</x-slot>

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Walk-in or phone booking</h1>
        <p class="mt-1 text-slate-600">Pick the time and package first — the room list refreshes to show only what is actually free.</p>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

        {{-- Step one is a plain GET so the free-room list is computed
             server-side against the real availability query, not guessed in
             the browser. --}}
        <x-card title="1. When and what type">
            <form method="GET" class="grid gap-4 sm:grid-cols-4">
                <div>
                    <x-input-label for="date" value="Date" />
                    <input type="date" id="date" name="date" value="{{ $startsAt->format('Y-m-d') }}"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div>
                    <x-input-label for="hour" value="Start hour" />
                    <select id="hour" name="hour" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        @foreach (range(0, 23) as $h)
                            <option value="{{ $h }}" @selected($startsAt->hour === $h)>{{ sprintf('%02d:00', $h) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="duration_package_id" value="Package" />
                    <select id="duration_package_id" name="duration_package_id" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        @foreach ($packages as $p)
                            <option value="{{ $p->id }}" @selected($selectedPackage?->id === $p->id)>{{ $p->hours }} hours</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="room_type_id" value="Room type" />
                    <div class="mt-1 flex gap-2">
                        <select id="room_type_id" name="room_type_id" class="block w-full rounded-md border-slate-300 shadow-sm">
                            <option value="">Any</option>
                            @foreach ($roomTypes as $type)
                                <option value="{{ $type->id }}" @selected((int) request('room_type_id') === $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <button class="shrink-0 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50">Find</button>
                    </div>
                </div>
            </form>

            @if ($window)
                <p class="mt-4 text-sm text-slate-600">
                    {{ $window->describeStay() }} &middot; room held until {{ $window->blockedUntil->format('H:i') }}
                    ({{ $window->bufferMinutes }} min turnover).
                    <strong>{{ $freeRooms->count() }}</strong> room{{ $freeRooms->count() === 1 ? '' : 's' }} free.
                </p>
            @endif
        </x-card>

        @if ($freeRooms->isEmpty())
            <x-alert tone="warning">
                Nothing is free for that window. Try a different hour, a shorter package, or another room type.
            </x-alert>
        @else
            <form method="POST" action="{{ route('staff.reservations.store') }}" class="space-y-6">
                @csrf
                <input type="hidden" name="date" value="{{ $startsAt->format('Y-m-d') }}">
                <input type="hidden" name="hour" value="{{ $startsAt->hour }}">
                <input type="hidden" name="duration_package_id" value="{{ $selectedPackage->id }}">

                <x-card title="2. Room">
                    <fieldset class="grid gap-2 sm:grid-cols-3">
                        <legend class="sr-only">Available rooms</legend>
                        @foreach ($freeRooms as $room)
                            <label class="flex items-center gap-3 rounded-lg border border-slate-200 p-3 cursor-pointer hover:border-slate-400 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-50">
                                <input type="radio" name="room_id" value="{{ $room->id }}"
                                       data-room-type="{{ $room->room_type_id }}"
                                       @checked($loop->first)
                                       class="border-slate-300 text-slate-900 focus:ring-slate-500"
                                       onchange="document.getElementById('room_type_field').value = this.dataset.roomType">
                                <span>
                                    <span class="block font-mono font-semibold text-slate-900">{{ $room->number }}</span>
                                    <span class="block text-xs text-slate-500">{{ $room->roomType->name }}</span>
                                </span>
                            </label>
                        @endforeach
                    </fieldset>

                    <input type="hidden" name="room_type_id" id="room_type_field" value="{{ $freeRooms->first()->room_type_id }}">
                </x-card>

                <x-card title="3. Guest">
                    @if ($similar->isNotEmpty())
                        {{-- Advisory only. A walk-in guest has no account, so
                             there is no identity to check against and refusing
                             a real person at the counter over a name collision
                             would be worse than the duplicate. --}}
                        <div class="mb-4">
                            <x-alert tone="warning">
                                Possible duplicate: a booking with a matching name or phone already overlaps this window
                                ({{ $similar->pluck('reference')->join(', ') }}). Check before continuing.
                            </x-alert>
                        </div>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="guest_name" value="Name" />
                            <input type="text" id="guest_name" name="guest_name" value="{{ old('guest_name') }}"
                                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm" required>
                        </div>
                        <div>
                            <x-input-label for="guest_phone" value="Phone" />
                            <input type="text" id="guest_phone" name="guest_phone" value="{{ old('guest_phone') }}"
                                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        </div>
                        <div>
                            <x-input-label for="guest_email" value="Email (optional)" />
                            <input type="email" id="guest_email" name="guest_email" value="{{ old('guest_email') }}"
                                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        </div>
                        <div>
                            <x-input-label for="channel" value="Taken by" />
                            <select id="channel" name="channel" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                @foreach ($channels as $channel)
                                    <option value="{{ $channel->value }}">{{ $channel->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="adults" value="Adults" />
                            <select id="adults" name="adults" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                @foreach (range(1, 6) as $n)
                                    <option value="{{ $n }}" @selected($n === 1)>{{ $n }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="children" value="Children" />
                            <select id="children" name="children" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                                @foreach (range(0, 4) as $n)
                                    <option value="{{ $n }}">{{ $n }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </x-card>

                @if ($extras->isNotEmpty())
                    <x-card title="4. Extras (optional)">
                        <ul class="divide-y divide-slate-100">
                            @foreach ($extras as $extra)
                                <li class="py-2.5 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-medium text-slate-900">{{ $extra->name }}</p>
                                        <p class="text-xs text-slate-500">
                                            <x-money :cents="$extra->price_cents" /> {{ strtolower($extra->pricing_basis->label()) }}
                                        </p>
                                    </div>
                                    <select name="extras[{{ $extra->id }}]" class="w-20 rounded-md border-slate-300 text-sm shadow-sm">
                                        @foreach (range(0, 5) as $n)
                                            <option value="{{ $n }}">{{ $n }}</option>
                                        @endforeach
                                    </select>
                                </li>
                            @endforeach
                        </ul>
                    </x-card>
                @endif

                <x-card title="5. Payment">
                    <div class="space-y-3">
                        @foreach ($paymentModes as $mode)
                            <label class="flex items-start gap-3 rounded-lg border border-slate-200 p-4 cursor-pointer hover:border-slate-400 has-[:checked]:border-slate-900 has-[:checked]:bg-slate-50">
                                <input type="radio" name="payment_mode" value="{{ $mode->value }}"
                                       @checked($mode === \App\Enums\PaymentMode::AtProperty)
                                       class="mt-1 border-slate-300 text-slate-900 focus:ring-slate-500">
                                <span>
                                    <span class="block font-medium text-slate-900">{{ $mode->label() }}</span>
                                    <span class="block text-sm text-slate-600">
                                        @if ($mode === \App\Enums\PaymentMode::AtProperty)
                                            Confirmed immediately. Record the payment on the booking screen once taken.
                                        @else
                                            Held unpaid until the guest pays online. Use this only if they will pay from their own device.
                                        @endif
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-4">
                        <x-input-label for="internal_notes" value="Internal note (optional)" />
                        <textarea id="internal_notes" name="internal_notes" rows="2"
                                  class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">{{ old('internal_notes') }}</textarea>
                    </div>

                    <button type="submit" class="mt-5 w-full rounded-md bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700">
                        Create booking
                    </button>

                    <p class="mt-3 text-xs text-slate-500">
                        The room is locked and re-checked as this is saved. If it has just gone, you will be told and nothing is written.
                    </p>
                </x-card>
            </form>
        @endif
    </div>
</x-app-layout>
