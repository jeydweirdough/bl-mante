<x-app-layout :title="$title" :description="$description" :canonical="$canonical" :noindex="false" :schema="$schema">

    <x-slot name="header">
        <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
            <a href="{{ route('home') }}" class="hover:text-slate-900">Home</a>
            <span class="mx-2" aria-hidden="true">/</span>
            <span class="text-slate-700">Contact</span>
        </nav>
        <h1 class="mt-2 text-2xl font-bold tracking-tight text-slate-900">{{ $c->get('contact.heading') }}</h1>
        <p class="mt-1 text-slate-600">{{ $c->get('contact.lead') }}</p>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8 lg:grid lg:grid-cols-5 lg:gap-10">

        {{-- Contact details first on mobile: most people arriving here want the
             phone number, not a form. --}}
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Front desk">
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-slate-500">Phone &mdash; staffed 24 hours</dt>
                        <dd class="mt-0.5">
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', config('hotel.contact_phone')) }}"
                               class="text-lg font-semibold text-slate-900 hover:underline">
                                {{ config('hotel.contact_phone') }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Email</dt>
                        <dd class="mt-0.5">
                            <a href="mailto:{{ config('hotel.contact_email') }}"
                               class="font-medium text-slate-900 hover:underline break-all">
                                {{ config('hotel.contact_email') }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Address</dt>
                        <dd class="mt-0.5">
                            <address class="not-italic text-slate-800">
                                {{ config('hotel.name') }}<br>
                                {{ config('hotel.address.street') }}<br>
                                {{ config('hotel.address.district') }}, {{ config('hotel.address.city') }} {{ config('hotel.address.postal_code') }}<br>
                                {{ config('hotel.address.country_name') }}
                            </address>
                        </dd>
                    </div>
                </dl>

                <a href="https://www.google.com/maps/search/?api=1&query={{ config('hotel.geo.latitude') }},{{ config('hotel.geo.longitude') }}"
                   target="_blank" rel="noopener noreferrer"
                   class="mt-5 inline-flex w-full justify-center rounded-md border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-900 hover:bg-slate-50">
                    Open in Maps
                </a>
            </x-card>

            @foreach ($c->get('contact.channels', []) as $channel)
                <div class="rounded-xl border border-slate-200 bg-white p-5">
                    <h2 class="font-semibold text-slate-900">{{ $channel['title'] }}</h2>
                    <p class="mt-1.5 text-sm text-slate-600 leading-relaxed">{{ $channel['body'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- The form --}}
        <div class="mt-8 lg:mt-0 lg:col-span-3">
            <x-card title="Send us a message" :subtitle="$c->get('contact.form_note')">
                <form method="POST" action="{{ route('contact.store') }}" class="space-y-4">
                    @csrf

                    {{-- Anti-spam, both invisible to a person: a field only a bot
                         fills in, and the time the page was rendered. Neither
                         asks a guest to prove anything. --}}
                    <div class="hidden" aria-hidden="true">
                        <label for="website">Leave this field empty</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>
                    <input type="hidden" name="rendered_at" value="{{ now()->timestamp }}">

                    {{-- Three fields to send a message. The form previously
                         asked six things at once, four of them optional, which
                         reads as work before you have said anything. The rest
                         are one click away and open automatically if a
                         validation error lands in them. --}}
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="name" value="Your name" />
                            <input type="text" id="name" name="name" required maxlength="120"
                                   value="{{ old('name', auth()->user()?->name) }}"
                                   autocomplete="name"
                                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <x-input-error :messages="$errors->get('name')" class="mt-1" />
                        </div>

                        <div>
                            <x-input-label for="email" value="Email" />
                            <input type="email" id="email" name="email" required maxlength="190"
                                   value="{{ old('email', auth()->user()?->email) }}"
                                   autocomplete="email"
                                   class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            <x-input-error :messages="$errors->get('email')" class="mt-1" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="message" value="Message" />
                        <textarea id="message" name="message" rows="6" required minlength="10" maxlength="4000"
                                  class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">{{ old('message') }}</textarea>
                        <x-input-error :messages="$errors->get('message')" class="mt-1" />
                    </div>

                    <x-disclosure label="Add a phone number, subject or booking reference"
                                  summary="optional"
                                  :open="$errors->hasAny(['phone', 'subject', 'reservation_reference'])">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="phone" value="Phone" />
                                <input type="tel" id="phone" name="phone" maxlength="40"
                                       value="{{ old('phone', auth()->user()?->phone) }}"
                                       autocomplete="tel"
                                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="reservation_reference" value="Booking reference" />
                                <input type="text" id="reservation_reference" name="reservation_reference" maxlength="20"
                                       value="{{ old('reservation_reference') }}" placeholder="BM-XXXXXX"
                                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500 font-mono">
                                <x-input-error :messages="$errors->get('reservation_reference')" class="mt-1" />
                            </div>

                            <div class="sm:col-span-2">
                                <x-input-label for="subject" value="Subject" />
                                <input type="text" id="subject" name="subject" maxlength="150"
                                       value="{{ old('subject') }}"
                                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                <x-input-error :messages="$errors->get('subject')" class="mt-1" />
                            </div>
                        </div>
                    </x-disclosure>

                    <button type="submit"
                            class="w-full inline-flex justify-center rounded-md bg-slate-900 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-700">
                        Send message
                    </button>

                    <p class="text-xs text-slate-500">
                        For anything urgent about a booking today, please call &mdash; the desk answers faster than the inbox.
                    </p>
                </form>
            </x-card>
        </div>
    </div>
</x-app-layout>
