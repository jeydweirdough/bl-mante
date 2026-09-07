<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-row property configuration with no financial meaning. Anything a
 * customer agreed to at booking time lives in PolicyVersion instead.
 */
class Setting extends Model
{
    protected $fillable = [
        'hotel_name',
        'timezone',
        'currency',
        'contact_email',
        'contact_phone',
        'address',
        'checkin_instructions',
    ];

    public static function instance(): self
    {
        return static::query()->firstOrCreate([], [
            'hotel_name' => config('hotel.name'),
            'timezone' => config('hotel.timezone'),
            'currency' => config('hotel.currency'),
            'contact_email' => config('hotel.contact_email'),
            'contact_phone' => config('hotel.contact_phone'),
        ]);
    }
}
