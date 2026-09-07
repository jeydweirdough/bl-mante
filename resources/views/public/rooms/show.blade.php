<x-app-layout :title="$title" :description="$description" :canonical="$canonical" :noindex="false" :schema="$schema">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('rooms.index') }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; All rooms</a>

        <div class="mt-4 lg:grid lg:grid-cols-3 lg:gap-8">
            <div class="lg:col-span-2 space-y-6">
                @if ($roomType->photos->isNotEmpty())
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($roomType->photos as $index => $photo)
                            <img src="{{ $photo->url() }}"
                                 alt="{{ $photo->alt_text ?? $roomType->name }}"
                                 class="rounded-xl object-cover w-full {{ $index === 0 ? 'sm:col-span-2 h-80' : 'h-44' }}">
                        @endforeach
                    </div>
                @endif

                <x-card>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-900">{{ $roomType->name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        Sleeps up to {{ $roomType->max_occupancy }}
                        @if ($roomType->bed_configuration) &middot; {{ $roomType->bed_configuration }} @endif
                        @if ($roomType->size_sqm) &middot; {{ $roomType->size_sqm }} m&sup2; @endif
                    </p>

                    <p class="mt-4 text-slate-700 whitespace-pre-line">{{ $roomType->description }}</p>

                    @if ($roomType->amenities->isNotEmpty())
                        <h2 class="mt-6 text-sm font-semibold text-slate-900">In this room</h2>
                        <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($roomType->amenities as $amenity)
                                <li class="flex items-start gap-2 text-sm text-slate-700">
                                    <span class="mt-1.5 h-1.5 w-1.5 rounded-full bg-slate-400 shrink-0"></span>
                                    <span>
                                        <span class="font-medium">{{ $amenity->name }}</span>
                                        @if ($amenity->description)
                                            <span class="text-slate-500"> — {{ $amenity->description }}</span>
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>

            <div class="mt-8 lg:mt-0 space-y-6">
                <x-card title="Package prices">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($roomType->packagePrices->sortBy(fn ($p) => $p->durationPackage->hours) as $price)
                            <li class="flex items-center justify-between py-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $price->durationPackage->hours }} hours</p>
                                    <p class="text-xs text-slate-500">{{ $price->durationPackage->name }}</p>
                                </div>
                                <p class="font-semibold text-slate-900"><x-money :cents="$price->price_cents" /></p>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-4 text-xs text-slate-500">
                        Extra hours after check-in are charged at
                        <x-money :cents="$roomType->extension_hourly_rate_cents" /> per hour, if the room is free.
                    </p>
                </x-card>

                <x-card title="Check availability">
                    <x-search-form
                        :packages="$packages"
                        :room-types="collect([$roomType])"
                        :selected-room-type-id="$roomType->id"
                        :defaults="['date' => now()->format('Y-m-d'), 'hour' => now()->addHour()->hour, 'duration_package_id' => $packages->first()?->id]" />
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
