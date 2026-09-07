<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\RoomType;
use App\Services\AuditLogger;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoomTypeController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $this->authorize('manage-catalogue');

        return view('admin.room-types.index', [
            'roomTypes' => RoomType::ordered()
                ->withCount('rooms')
                ->with(['amenities', 'packagePrices.durationPackage'])
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage-catalogue');

        return view('admin.room-types.form', [
            'roomType' => new RoomType(['base_occupancy' => 2, 'max_occupancy' => 2, 'is_active' => true]),
            'amenities' => Amenity::active()->orderBy('name')->get(),
            'selectedAmenities' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-catalogue');

        $data = $this->validated($request);

        $roomType = RoomType::create($data['attributes']);
        $roomType->amenities()->sync($data['amenities']);

        $this->audit->record('room_type.created', $roomType, $request->user(), after: $data['attributes']);

        return redirect()
            ->route('admin.room-types.index')
            ->with('status', "{$roomType->name} created. Set its package prices next.");
    }

    public function edit(RoomType $roomType): View
    {
        $this->authorize('manage-catalogue');

        return view('admin.room-types.form', [
            'roomType' => $roomType,
            'amenities' => Amenity::active()->orderBy('name')->get(),
            'selectedAmenities' => $roomType->amenities->pluck('id')->all(),
        ]);
    }

    public function update(Request $request, RoomType $roomType): RedirectResponse
    {
        $this->authorize('manage-catalogue');

        $data = $this->validated($request, $roomType);
        $before = $roomType->only(array_keys($data['attributes']));

        $roomType->update($data['attributes']);
        $roomType->amenities()->sync($data['amenities']);

        $this->audit->record('room_type.updated', $roomType, $request->user(), $before, $data['attributes']);

        return redirect()
            ->route('admin.room-types.index')
            ->with('status', "{$roomType->name} updated. Existing bookings keep the price they were made at.");
    }

    private function validated(Request $request, ?RoomType $roomType = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('room_types', 'slug')->ignore($roomType)],
            'short_description' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_occupancy' => ['required', 'integer', 'between:1,10'],
            'max_occupancy' => ['required', 'integer', 'between:1,10', 'gte:base_occupancy'],
            'bed_configuration' => ['nullable', 'string', 'max:120'],
            'size_sqm' => ['nullable', 'integer', 'between:1,1000'],
            'extension_hourly_rate' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
            'is_active' => ['nullable', 'boolean'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
        ]);

        return [
            'attributes' => [
                'name' => $validated['name'],
                'slug' => $validated['slug'] ?: Str::slug($validated['name']),
                'short_description' => $validated['short_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'base_occupancy' => $validated['base_occupancy'],
                'max_occupancy' => $validated['max_occupancy'],
                'bed_configuration' => $validated['bed_configuration'] ?? null,
                'size_sqm' => $validated['size_sqm'] ?? null,
                'extension_hourly_rate_cents' => Money::parse((string) $validated['extension_hourly_rate']),
                'sort_order' => $validated['sort_order'] ?? 0,
                'is_active' => $request->boolean('is_active'),
            ],
            'amenities' => $validated['amenities'] ?? [],
        ];
    }
}
