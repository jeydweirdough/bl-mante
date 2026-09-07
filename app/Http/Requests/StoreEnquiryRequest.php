<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A message from the contact form.
 *
 * Open to the public and unauthenticated, so it is the most exposed write in
 * the application. Two cheap defences, neither of which inconveniences a real
 * person: a honeypot field a human never sees, and a minimum time on the page.
 * Rate limiting sits on the route.
 *
 * Deliberately not a CAPTCHA. It would cost every legitimate guest something
 * to stop a class of spam these two measures already handle, and a hotel that
 * makes people prove they are human before asking a question loses enquiries.
 */
class StoreEnquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['nullable', 'string', 'max:150'],
            'reservation_reference' => ['nullable', 'string', 'max:20'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],

            // The honeypot. Hidden from people, irresistible to naive bots, so
            // anything arriving with it filled in is not a guest.
            'website' => ['prohibited'],

            'rendered_at' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'message.min' => 'Please tell us a little more so we can answer properly.',
            'website.prohibited' => 'That submission looked automated. Please try again.',
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // A form completed in under three seconds was not typed by a
            // person. The field is optional so a visitor with a stale page or
            // scripts disabled is never blocked by its absence.
            $rendered = $this->integer('rendered_at');

            if ($rendered > 0 && (now()->timestamp - $rendered) < 3) {
                $validator->errors()->add('message', 'That was submitted very quickly. Please try again.');
            }
        });
    }

    /** The columns to persist, with the anti-spam fields dropped. */
    public function toEnquiry(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->trim()->lower()->toString(),
            'phone' => $this->filled('phone') ? $this->string('phone')->trim()->toString() : null,
            'subject' => $this->filled('subject') ? $this->string('subject')->trim()->toString() : null,
            'reservation_reference' => $this->filled('reservation_reference')
                ? $this->string('reservation_reference')->trim()->upper()->toString()
                : null,
            'message' => $this->string('message')->trim()->toString(),
            'user_id' => $this->user()?->id,
            'ip_address' => $this->ip(),
            'user_agent' => substr((string) $this->userAgent(), 0, 255) ?: null,
        ];
    }
}
