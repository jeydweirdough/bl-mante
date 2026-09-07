<?php

namespace App\Http\Requests\Staff;

use App\Enums\PaymentMode;
use App\Enums\ReservationChannel;
use App\Models\DurationPackage;
use App\Models\RoomType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A booking taken at the counter or over the phone.
 *
 * Differs from the customer form in three ways: the guest may have no account
 * at all, staff choose the physical room, and the start time may be in the
 * current hour because the guest is standing there.
 */
class StoreWalkInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPersonnel() ?? false;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in([ReservationChannel::WalkIn->value, ReservationChannel::Phone->value])],

            'room_type_id' => ['required', Rule::exists('room_types', 'id')->where('is_active', true)],
            'room_id' => ['nullable', Rule::exists('rooms', 'id')->where('is_bookable', true)],
            'duration_package_id' => ['required', Rule::exists('duration_packages', 'id')->where('is_active', true)],

            'date' => ['required', 'date_format:Y-m-d'],
            'hour' => ['required', 'integer', 'between:0,23'],

            'adults' => ['required', 'integer', 'between:1,10'],
            'children' => ['required', 'integer', 'between:0,10'],

            // Either an existing customer account, or a name for someone who
            // has none. The database enforces the same rule.
            'user_id' => ['nullable', 'exists:users,id'],
            'guest_name' => ['required_without:user_id', 'nullable', 'string', 'max:120'],
            'guest_email' => ['nullable', 'email', 'max:190'],
            'guest_phone' => ['nullable', 'string', 'max:40'],

            'payment_mode' => ['required', Rule::enum(PaymentMode::class)],

            'extras' => ['nullable', 'array'],
            'extras.*' => ['nullable', 'integer', 'between:0,20'],

            'internal_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'guest_name.required_without' => 'Enter the guest name, or pick an existing customer account.',
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // The desk may book the hour already under way for someone
            // standing at the counter, but not one that has fully passed.
            if ($this->startsAt()->addHour()->isPast()) {
                $validator->errors()->add('hour', 'That start time has already passed.');
            }

            if ($this->partySize() > $this->roomType()->max_occupancy) {
                $validator->errors()->add('adults', sprintf(
                    'A %s sleeps up to %d guests.',
                    $this->roomType()->name,
                    $this->roomType()->max_occupancy,
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

    public function channel(): ReservationChannel
    {
        return ReservationChannel::from($this->string('channel')->toString());
    }

    public function paymentMode(): PaymentMode
    {
        return PaymentMode::from($this->string('payment_mode')->toString());
    }

    public function existingCustomer(): ?User
    {
        return $this->filled('user_id') ? User::find($this->integer('user_id')) : null;
    }

    public function partySize(): int
    {
        return $this->integer('adults') + $this->integer('children');
    }

    /** @return array<int, array{quantity:int}> */
    public function extraSelections(): array
    {
        return collect($this->input('extras', []))
            ->map(fn ($q) => (int) $q)
            ->filter(fn (int $q) => $q > 0)
            ->mapWithKeys(fn (int $q, $id) => [(int) $id => ['quantity' => $q]])
            ->all();
    }
}
