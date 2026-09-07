<x-app-layout>
    <x-slot name="title">Extras</x-slot>

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Extras</h1>
        <p class="mt-1 text-slate-600">
            Disabling an extra stops it being offered. Bookings that already include it keep their own name and price snapshot,
            which is why it is never deleted.
        </p>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-card title="Add an extra">
            <form method="POST" action="{{ route('admin.extras.store') }}" class="grid gap-3 sm:grid-cols-6">
                @csrf
                <div class="sm:col-span-2">
                    <x-input-label for="name" value="Name" />
                    <input type="text" id="name" name="name" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div>
                    <x-input-label for="price" value="Price" />
                    <input type="number" step="0.01" min="0" id="price" name="price" required
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="pricing_basis" value="Charged" />
                    <select id="pricing_basis" name="pricing_basis" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        @foreach ($bases as $basis)
                            <option value="{{ $basis->value }}">{{ $basis->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end">
                    <button class="w-full rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Add</button>
                </div>
                <div class="sm:col-span-6">
                    <x-input-label for="description" value="Description (optional)" />
                    <input type="text" id="description" name="description" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div class="sm:col-span-6">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="available_during_stay" value="1" checked
                               class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                        <span>Can also be added after check-in</span>
                    </label>
                </div>
            </form>
        </x-card>

        <div class="space-y-3">
            @foreach ($extras as $extra)
                <x-card>
                    <form method="POST" action="{{ route('admin.extras.update', $extra) }}" class="grid gap-3 sm:grid-cols-6 items-end">
                        @csrf
                        @method('PUT')

                        <div class="sm:col-span-2">
                            <x-input-label value="Name" />
                            <input type="text" name="name" value="{{ $extra->name }}"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        </div>
                        <div>
                            <x-input-label value="Price" />
                            <input type="number" step="0.01" min="0" name="price"
                                   value="{{ number_format($extra->price_cents / 100, 2, '.', '') }}"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        </div>
                        <div>
                            <x-input-label value="Charged" />
                            <select name="pricing_basis" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                                @foreach ($bases as $basis)
                                    <option value="{{ $basis->value }}" @selected($extra->pricing_basis === $basis)>{{ $basis->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label value="Order" />
                            <input type="number" name="sort_order" value="{{ $extra->sort_order }}" min="0" max="999"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        </div>
                        <div class="flex gap-2">
                            <button class="flex-1 rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50">Save</button>
                        </div>

                        <div class="sm:col-span-6 flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name="available_during_stay" value="1"
                                           @checked($extra->available_during_stay)
                                           class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                                    <span>Addable during a stay</span>
                                </label>

                                @if ($extra->is_active)
                                    <x-badge classes="bg-emerald-100 text-emerald-800 ring-emerald-600/20">Offered</x-badge>
                                @else
                                    <x-badge classes="bg-neutral-200 text-neutral-700 ring-neutral-500/20">Not offered</x-badge>
                                @endif

                                <span class="text-xs text-slate-500">
                                    On {{ $extra->reservation_extras_count }} {{ Str::plural('booking', $extra->reservation_extras_count) }}
                                </span>
                            </div>

                            <input type="text" name="description" value="{{ $extra->description }}" placeholder="Description"
                                   class="w-full sm:w-80 rounded-md border-slate-300 text-sm shadow-sm">
                        </div>
                    </form>

                    <form method="POST" action="{{ route('admin.extras.toggle', $extra) }}" class="mt-3">
                        @csrf
                        <button class="text-sm font-semibold {{ $extra->is_active ? 'text-rose-700 hover:underline' : 'text-emerald-700 hover:underline' }}">
                            {{ $extra->is_active ? 'Stop offering this' : 'Offer this again' }}
                        </button>
                    </form>
                </x-card>
            @endforeach
        </div>
    </div>
</x-app-layout>
