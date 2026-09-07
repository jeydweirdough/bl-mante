<?php

namespace App\Http\Requests;

use App\Models\DurationPackage;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The public availability search: a date, a start hour, and a package.
 *
 * The start hour is a separate field rather than part of a datetime because
 * reservations start on the hour and nothing else is permitted -- making that
 * structural in the form is better than validating it out of a free-text
 * datetime afterwards.
 */
class AvailabilitySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hour' => ['required', 'integer', 'between:0,23'],
            'duration_package_id' => ['required', Rule::exists('duration_packages', 'id')->where('is_active', true)],
            'room_type_id' => ['nullable', Rule::exists('room_types', 'id')->where('is_active', true)],
            'adults' => ['nullable', 'integer', 'between:1,10'],
            'children' => ['nullable', 'integer', 'between:0,10'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'Choose today or a later date.',
            'hour.between' => 'Reservations start on the hour, between 00:00 and 23:00.',
        ];
    }

    /**
     * The requested start, on the hour.
     *
     * No timezone conversion: the application clock is the hotel's clock (see
     * config/app.php), so what the guest picks is what gets stored.
     */
    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H',
            $this->composeDateTime($this->string('date')->toString(), $this->integer('hour')),
        )->startOfHour();
    }

    public function package(): DurationPackage
    {
        return DurationPackage::findOrFail($this->integer('duration_package_id'));
    }

    public function roomType(): ?RoomType
    {
        return $this->filled('room_type_id')
            ? RoomType::find($this->integer('room_type_id'))
            : null;
    }

    public function partySize(): int
    {
        return max(1, $this->integer('adults', 1) + $this->integer('children', 0));
    }

    /** Not named date(): Request::date() already exists and is public. */
    private function composeDateTime(string $date, int $hour): string
    {
        return $date.' '.str_pad((string) $hour, 2, '0', STR_PAD_LEFT);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'adults' => $this->input('adults', 1),
            'children' => $this->input('children', 0),
        ]);
    }
}
