<?php

namespace App\Models;

use Database\Factories\RoomTypeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomType extends Model
{
    /** @use HasFactory<RoomTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'short_description',
        'base_occupancy',
        'max_occupancy',
        'bed_configuration',
        'size_sqm',
        'extension_hourly_rate_cents',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'base_occupancy' => 'integer',
            'max_occupancy' => 'integer',
            'size_sqm' => 'integer',
            'extension_hourly_rate_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function bookableRooms(): HasMany
    {
        return $this->hasMany(Room::class)->where('is_bookable', true);
    }

    public function amenities(): BelongsToMany
    {
        return $this->belongsToMany(Amenity::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(RoomTypePhoto::class)->orderBy('sort_order');
    }

    public function packagePrices(): HasMany
    {
        return $this->hasMany(PackagePrice::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function coverPhoto(): ?RoomTypePhoto
    {
        return $this->photos->firstWhere('is_cover', true) ?? $this->photos->first();
    }

    /** The price of one duration package for this room type, in minor units. */
    public function priceFor(DurationPackage $package): ?int
    {
        return $this->packagePrices
            ->firstWhere('duration_package_id', $package->id)
            ?->price_cents;
    }

    public function cheapestPriceCents(): ?int
    {
        return $this->packagePrices->where('is_active', true)->min('price_cents');
    }
}
