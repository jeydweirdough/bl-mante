{{-- Title, description, canonical and breadcrumb markup come from the
     controller so the meta stays next to the query that builds it. --}}
<x-app-layout :title="$title" :description="$description" :canonical="$canonical" :noindex="false" :schema="$schema">

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Rooms and packages</h1>
        <p class="mt-1 text-slate-600">Prices are for the whole package. Every stay is followed by a turnover break before the room is offered again.</p>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        @foreach ($roomTypes as $roomType)
            <article class="bg-white rounded-xl border border-slate-200 overflow-hidden lg:flex">
                <div class="lg:w-80 shrink-0">
                    @if ($photo = $roomType->coverPhoto())
                        <img src="{{ $photo->url() }}" alt="{{ $photo->alt_text ?? $roomType->name }}" class="h-56 lg:h-full w-full object-cover">
                    @else
                        <div class="h-56 lg:h-full w-full bg-slate-100 flex items-center justify-center text-slate-400 text-sm">No photo yet</div>
                    @endif
                </div>

                <div class="p-6 flex-1">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ $roomType->name }}</h2>
                            <p class="text-sm text-slate-500">
                                Sleeps {{ $roomType->max_occupancy }}
                                @if ($roomType->bed_configuration) &middot; {{ $roomType->bed_configuration }} @endif
                                @if ($roomType->size_sqm) &middot; {{ $roomType->size_sqm }} m&sup2; @endif
                            </p>
                        </div>
                        <x-badge classes="bg-slate-100 text-slate-700 ring-slate-500/20">
                            {{ $roomType->bookable_rooms_count }} room{{ $roomType->bookable_rooms_count === 1 ? '' : 's' }}
                        </x-badge>
                    </div>

                    <p class="mt-3 text-sm text-slate-600">{{ $roomType->description ?: $roomType->short_description }}</p>

                    @if ($roomType->amenities->isNotEmpty())
                        <ul class="mt-4 flex flex-wrap gap-2">
                            @foreach ($roomType->amenities as $amenity)
                                <li><x-badge>{{ $amenity->name }}</x-badge></li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-5 grid gap-3 sm:grid-cols-4">
                        @foreach ($roomType->packagePrices->sortBy(fn ($p) => $p->durationPackage->hours) as $price)
                            <div class="rounded-lg border border-slate-200 px-3 py-2">
                                <p class="text-xs uppercase tracking-wide text-slate-500">{{ $price->durationPackage->hours }} hours</p>
                                <p class="mt-1 font-semibold text-slate-900"><x-money :cents="$price->price_cents" /></p>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 flex gap-3">
                        <a href="{{ route('rooms.show', $roomType) }}" class="inline-flex rounded-md border border-slate-300 px-3.5 py-2 text-sm font-semibold text-slate-900 hover:bg-slate-50">Details</a>
                        <a href="{{ route('availability', ['room_type_id' => $roomType->id, 'date' => now()->format('Y-m-d'), 'hour' => now()->addHour()->hour, 'duration_package_id' => $packages->first()?->id]) }}"
                           class="inline-flex rounded-md bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-700">Check availability</a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>
</x-app-layout>
