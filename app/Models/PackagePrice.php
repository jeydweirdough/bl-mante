<?php

namespace App\Models;

use Database\Factories\PackagePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackagePrice extends Model
{
    /** @use HasFactory<PackagePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'room_type_id',
        'duration_package_id',
        'price_cents',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    public function durationPackage(): BelongsTo
    {
        return $this->belongsTo(DurationPackage::class);
    }
}
