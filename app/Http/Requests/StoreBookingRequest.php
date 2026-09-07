<?php

namespace App\Http\Requests;

use App\Enums\PaymentMode;
use App\Models\DurationPackage;
use App\Models\RoomType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'room_type_id' => ['required', Rule::exists('room_types', 'id')->where('is_active', true)],
            'duration_package_id' => ['required', Rule::exists('duration_packages', 'id')->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'hour' => ['required', 'integer', 'between:0,23'],

            'adults' => ['required', 'integer', 'between:1,10'],
            'children' => ['required', 'integer', 'between:0,10'],

            'payment_mode' => ['required', Rule::enum(PaymentMode::class)],

            // Extras arrive as extras[<id>] = quantity.
            'extras' => ['nullable', 'array'],
            'extras.*' => ['nullable', 'integer', 'between:0,20'],

            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'terms.accepted' => 'Please confirm you have read the cancellation terms.',
            'date.after_or_equal' => 'That date has already passed.',
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->startsAt()->isPast()) {
                $validator->errors()->add('hour', 'That start time has already passed.');
            }

            $roomType = $this->roomType();

            if ($roomType && $this->partySize() > $roomType->max_occupancy) {
                $validator->errors()->add('adults', sprintf(
                    'A %s sleeps up to %d guests.',
                    $roomType->name,
                    $roomType->max_occupancy,
                ));
            }
        });
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat(
            'Y-m-d H',
            $this->string('date').' '.str_pad((string) $this->integer('hour'), 2, '0', STR_PAD_LEFT),
        )->startOfHour();
    }

    public function roomType(): RoomType
    {
        return RoomType::findOrFail($this->integer('room_type_id'));
    }

    public function package(): DurationPackage
    {
        return DurationPackage::findOrFail($this->integer('duration_package_id'));
    }

    public function paymentMode(): PaymentMode
    {
        return PaymentMode::from($this->string('payment_mode')->toString());
    }

    public function partySize(): int
    {
        return $this->integer('adults') + $this->integer('children');
    }

    /**
     * Extras in the shape PricingService expects, with the zeroes dropped.
     *
     * @return array<int, array{quantity:int}>
     */
    public function extraSelections(): array
    {
        return collect($this->input('extras', []))
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn (int $quantity) => $quantity > 0)
            ->mapWithKeys(fn (int $quantity, $id) => [(int) $id => ['quantity' => $quantity]])
            ->all();
    }
}
