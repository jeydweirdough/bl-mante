<x-app-layout>
    <x-slot name="title">Pricing</x-slot>

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Package prices</h1>
        <p class="mt-1 text-slate-600">One price per room type per duration. Editing affects new bookings only.</p>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-alert tone="info">
            Existing reservations carry their own price snapshot, so changing anything here never alters a booking
            that has already been made.
        </x-alert>

        <form method="POST" action="{{ route('admin.pricing.update') }}">
            @csrf
            @method('PUT')

            <x-card bodyClass="p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-700">Room type</th>
                                @foreach ($packages as $package)
                                    <th class="px-4 py-3 text-left font-semibold text-slate-700">
                                        {{ $package->hours }} hours
                                        @unless ($package->is_active)
                                            <span class="block text-xs font-normal text-slate-400">inactive</span>
                                        @endunless
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($roomTypes as $roomType)
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-slate-900">{{ $roomType->name }}</p>
                                        @unless ($roomType->is_active)
                                            <span class="text-xs text-slate-400">inactive</span>
                                        @endunless
                                    </td>
                                    @foreach ($packages as $package)
                                        @php($price = $grid[$roomType->id][$package->id] ?? null)
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-1">
                                                <span class="text-slate-400">{{ config('hotel.currency_symbol') }}</span>
                                                <input type="number" step="0.01" min="0"
                                                       name="prices[{{ $roomType->id }}][{{ $package->id }}]"
                                                       value="{{ $price ? number_format($price->price_cents / 100, 2, '.', '') : '' }}"
                                                       placeholder="not sold"
                                                       class="w-28 rounded-md border-slate-300 text-sm shadow-sm">
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <p class="mt-3 text-xs text-slate-500">
                Leaving a cell blank means that room type is not sold at that duration; it will not appear in search results for it.
            </p>

            <button type="submit" class="mt-4 rounded-md bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                Save prices
            </button>
        </form>

        <x-card title="Extension rates" subtitle="Charged per extra hour when a stay is extended. Edit these on each room type.">
            <ul class="divide-y divide-slate-100 text-sm">
                @foreach ($roomTypes as $roomType)
                    <li class="py-2 flex justify-between">
                        <span class="text-slate-700">{{ $roomType->name }}</span>
                        <span class="text-slate-900"><x-money :cents="$roomType->extension_hourly_rate_cents" /> / hour</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('admin.room-types.index') }}" class="mt-3 inline-block text-sm font-semibold text-slate-900 hover:underline">
                Edit room types &rarr;
            </a>
        </x-card>
    </div>
</x-app-layout>
