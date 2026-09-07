<x-app-layout>
    <x-slot name="title">Room types</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Room types</h1>
            <a href="{{ route('admin.room-types.create') }}"
               class="inline-flex rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Add room type</a>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-4">
        @foreach ($roomTypes as $roomType)
            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <h2 class="font-semibold text-slate-900">{{ $roomType->name }}</h2>
                            @unless ($roomType->is_active)
                                <x-badge classes="bg-neutral-200 text-neutral-700 ring-neutral-500/20">Inactive</x-badge>
                            @endunless
                        </div>
                        <p class="mt-1 text-sm text-slate-600">{{ $roomType->short_description }}</p>
                        <p class="mt-1 text-xs text-slate-500">
                            {{ $roomType->rooms_count }} physical {{ Str::plural('room', $roomType->rooms_count) }}
                            &middot; sleeps {{ $roomType->max_occupancy }}
                            &middot; extensions at <x-money :cents="$roomType->extension_hourly_rate_cents" />/hour
                        </p>

                        @if ($roomType->amenities->isNotEmpty())
                            <ul class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($roomType->amenities as $amenity)
                                    <li><x-badge>{{ $amenity->name }}</x-badge></li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="text-right shrink-0">
                        <ul class="text-sm space-y-0.5">
                            @foreach ($roomType->packagePrices->sortBy(fn ($p) => $p->durationPackage->hours) as $price)
                                <li class="text-slate-600">
                                    {{ $price->durationPackage->hours }}h
                                    <span class="text-slate-900 font-medium"><x-money :cents="$price->price_cents" /></span>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ route('admin.room-types.edit', $roomType) }}"
                           class="mt-3 inline-flex rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Edit</a>
                    </div>
                </div>
            </x-card>
        @endforeach

        <p class="text-xs text-slate-500">
            Room types are deactivated rather than deleted, so bookings that reference them keep resolving.
        </p>
    </div>
</x-app-layout>
