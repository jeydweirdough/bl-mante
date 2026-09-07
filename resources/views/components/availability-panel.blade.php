@props(['row' => null, 'package', 'startsAt', 'adults' => 1, 'children' => 0, 'roomType'])

{{--
    The availability verdict for one room type.

    Four distinct states, because each asks something different of the visitor:
    bookable, nearly gone, full but free later, and not sold at this length.
    "Unavailable" on its own is a dead end.
--}}

@php
    $bookingLink = fn ($window) => route('booking.create', [
        'room_type_id' => $roomType->id,
        'duration_package_id' => $package?->id,
        'date' => $window->startsAt->format('Y-m-d'),
        'hour' => $window->startsAt->hour,
        'adults' => $adults,
        'children' => $children,
    ]);

    $retryLink = fn ($window) => route('rooms.index', [
        'duration_package_id' => $package?->id,
        'date' => $window->startsAt->format('Y-m-d'),
        'hour' => $window->startsAt->hour,
        'adults' => $adults,
        'children' => $children,
    ]).'#room-'.$roomType->slug;
@endphp

@if ($row === null || ! $row->isSellable())
    {{-- Not priced for this package: the type exists but is not sold at this
         length, which is a different thing from being booked out. --}}
    <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-inset ring-slate-200">
        <p class="font-medium text-slate-800">Not offered as a {{ $package?->hours }}-hour stay</p>
        <p class="mt-0.5">Try another package length, or call us.</p>
    </div>

@elseif ($row->isAvailable())
    <div class="rounded-lg bg-emerald-50 px-4 py-3 ring-1 ring-inset ring-emerald-600/20">
        <p class="flex items-center gap-2 text-sm font-semibold text-emerald-900">
            <svg aria-hidden="true" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 011.4-1.4l3.8 3.8 6.8-6.8a1 1 0 011.4 0z" clip-rule="evenodd"/>
            </svg>
            {{ $row->roomsFree() }} {{ Str::plural('room', $row->roomsFree()) }} free
        </p>

        <p class="mt-1 text-sm text-emerald-800">
            {{ $row->requested->startsAt->format('D j M') }},
            {{ $row->requested->startsAt->format('H:i') }}–{{ $row->requested->endsAt->format('H:i') }}
        </p>

        @if ($row->isNearlyGone())
            <p class="mt-1 text-xs font-medium text-amber-800">
                Almost gone for this time.
            </p>
        @endif
    </div>

    <a href="{{ $bookingLink($row->requested) }}"
       class="mt-3 inline-flex w-full justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
        Book this room
    </a>

@elseif ($row->nextWindow)
    {{-- Full now, free later. The whole point of the forward search. --}}
    <div class="rounded-lg bg-amber-50 px-4 py-3 ring-1 ring-inset ring-amber-600/20">
        <p class="text-sm font-semibold text-amber-900">
            Fully booked at {{ $row->requested->startsAt->format('H:i') }}
            on {{ $row->requested->startsAt->format('D j M') }}
        </p>
        <p class="mt-1 text-sm text-amber-800">{{ $row->unavailableMessage() }}</p>
        @if ($wait = $row->waitDescription())
            <p class="mt-0.5 text-xs text-amber-700">That is {{ $wait }} later.</p>
        @endif
    </div>

    <div class="mt-3 grid gap-2 sm:grid-cols-2">
        <a href="{{ $retryLink($row->nextWindow) }}"
           class="inline-flex justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50">
            Show that time
        </a>
        <a href="{{ $bookingLink($row->nextWindow) }}"
           class="inline-flex justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
            Book {{ $row->nextWindow->startsAt->format('D H:i') }}
        </a>
    </div>

@else
    {{-- Full for the whole lookahead. Nothing to click, so hand them a phone
         number rather than a shrug. --}}
    <div class="rounded-lg bg-rose-50 px-4 py-3 ring-1 ring-inset ring-rose-600/20">
        <p class="text-sm font-semibold text-rose-900">Fully booked</p>
        <p class="mt-1 text-sm text-rose-800">{{ $row->unavailableMessage() }}</p>
    </div>

    <div class="mt-3 grid gap-2 sm:grid-cols-2">
        <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('hotel.contact_phone')) }}"
           class="inline-flex justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
            Call the desk
        </a>
        <a href="{{ route('contact') }}"
           class="inline-flex justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50">
            Send a message
        </a>
    </div>
@endif
