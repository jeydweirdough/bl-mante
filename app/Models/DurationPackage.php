<?php

namespace App\Models;

use Database\Factories\DurationPackageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DurationPackage extends Model
{
    /** @use HasFactory<DurationPackageFactory> */
    use HasFactory;

    protected $fillable = ['hours', 'name', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'hours' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
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
        return $query->orderBy('sort_order')->orderBy('hours');
    }
}
