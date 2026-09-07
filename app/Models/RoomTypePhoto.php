<?php

namespace App\Models;

use Database\Factories\RoomTypePhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTypePhoto extends Model
{
    /** @use HasFactory<RoomTypePhotoFactory> */
    use HasFactory;

    protected $fillable = ['room_type_id', 'path', 'alt_text', 'sort_order', 'is_cover'];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Photos are seeded as remote or public-path URLs; both are handed to the
     * view unchanged so the seeder can point at placeholder imagery without a
     * storage step.
     */
    public function url(): string
    {
        return str_starts_with($this->path, 'http') ? $this->path : asset($this->path);
    }
}
