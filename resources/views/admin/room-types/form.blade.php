<x-app-layout>
    <x-slot name="title">{{ $roomType->exists ? 'Edit '.$roomType->name : 'New room type' }}</x-slot>

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">
            {{ $roomType->exists ? 'Edit '.$roomType->name : 'New room type' }}
        </h1>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <form method="POST"
              action="{{ $roomType->exists ? route('admin.room-types.update', $roomType) : route('admin.room-types.store') }}"
              class="space-y-6">
            @csrf
            @if ($roomType->exists) @method('PUT') @endif

            <x-card title="Basics">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input-label for="name" value="Name" />
                        <input type="text" id="name" name="name" value="{{ old('name', $roomType->name) }}" required
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="short_description" value="One-line description" />
                        <input type="text" id="short_description" name="short_description"
                               value="{{ old('short_description', $roomType->short_description) }}" maxlength="255"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="description" value="Full description" />
                        <textarea id="description" name="description" rows="4"
                                  class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">{{ old('description', $roomType->description) }}</textarea>
                    </div>
                    <div>
                        <x-input-label for="base_occupancy" value="Standard occupancy" />
                        <input type="number" id="base_occupancy" name="base_occupancy" min="1" max="10"
                               value="{{ old('base_occupancy', $roomType->base_occupancy) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div>
                        <x-input-label for="max_occupancy" value="Maximum occupancy" />
                        <input type="number" id="max_occupancy" name="max_occupancy" min="1" max="10"
                               value="{{ old('max_occupancy', $roomType->max_occupancy) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        <p class="mt-1 text-xs text-slate-500">Searches for a larger party will not offer this type.</p>
                    </div>
                    <div>
                        <x-input-label for="bed_configuration" value="Beds" />
                        <input type="text" id="bed_configuration" name="bed_configuration"
                               value="{{ old('bed_configuration', $roomType->bed_configuration) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div>
                        <x-input-label for="size_sqm" value="Size (m²)" />
                        <input type="number" id="size_sqm" name="size_sqm" min="1" max="1000"
                               value="{{ old('size_sqm', $roomType->size_sqm) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                </div>
            </x-card>

            <x-card title="Extensions">
                <x-input-label for="extension_hourly_rate" value="Rate per extra hour ({{ config('hotel.currency_symbol') }})" />
                <input type="number" step="0.01" min="0" id="extension_hourly_rate" name="extension_hourly_rate"
                       value="{{ old('extension_hourly_rate', number_format($roomType->extension_hourly_rate_cents / 100, 2, '.', '')) }}"
                       class="mt-1 block w-full sm:w-48 rounded-md border-slate-300 shadow-sm">
                <p class="mt-1 text-xs text-slate-500">
                    Snapshotted onto each extension, so changing it never alters a guest who has already been granted extra hours.
                </p>
            </x-card>

            <x-card title="Amenities">
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach ($amenities as $amenity)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="amenities[]" value="{{ $amenity->id }}"
                                   @checked(in_array($amenity->id, old('amenities', $selectedAmenities)))
                                   class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                            <span>{{ $amenity->name }}</span>
                        </label>
                    @endforeach
                </div>
            </x-card>

            <x-card>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="sort_order" value="Sort order" />
                        <input type="number" id="sort_order" name="sort_order" min="0" max="999"
                               value="{{ old('sort_order', $roomType->sort_order ?? 0) }}"
                               class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="is_active" value="1"
                                   @checked(old('is_active', $roomType->is_active ?? true))
                                   class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                            <span>Offered to guests</span>
                        </label>
                    </div>
                </div>

                <div class="mt-5 flex gap-3">
                    <button type="submit" class="rounded-md bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                        {{ $roomType->exists ? 'Save changes' : 'Create room type' }}
                    </button>
                    <a href="{{ route('admin.room-types.index') }}"
                       class="rounded-md border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50">Cancel</a>
                </div>
            </x-card>
        </form>
    </div>
</x-app-layout>
