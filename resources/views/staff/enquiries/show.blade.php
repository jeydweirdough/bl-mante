<x-app-layout>
    <x-slot name="title">{{ $enquiry->subjectLine() }}</x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <a href="{{ route('staff.enquiries.index') }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; All enquiries</a>

        <h1 class="mt-3 text-2xl font-bold tracking-tight text-slate-900">{{ $enquiry->subjectLine() }}</h1>
        <p class="mt-1 text-slate-600">
            Received {{ $enquiry->created_at->format('l j F Y, H:i') }}
            @if ($enquiry->readBy)
                &middot; first opened by {{ $enquiry->readBy->name }}
            @endif
        </p>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 space-y-6">
                <x-card title="Message">
                    {{-- Escaped, and rendered with whitespace preserved rather
                         than as markup: everything here was typed by a member
                         of the public. --}}
                    <p class="whitespace-pre-line leading-relaxed text-slate-800">{{ $enquiry->message }}</p>
                </x-card>

                <x-card title="Handling">
                    <form method="POST" action="{{ route('staff.enquiries.update', $enquiry) }}" class="space-y-4">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="internal_notes" value="Internal notes" />
                            <textarea id="internal_notes" name="internal_notes" rows="4"
                                      class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">{{ old('internal_notes', $enquiry->internal_notes) }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Staff only. The guest never sees this.</p>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="mark_replied" value="1" @checked($enquiry->replied_at)
                                   class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                            <span>We have answered this</span>
                        </label>

                        <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Save</button>
                    </form>
                </x-card>
            </div>

            <div class="space-y-4">
                <x-card title="Sender">
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-slate-500">Name</dt>
                            <dd class="text-slate-900">{{ $enquiry->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">Email</dt>
                            <dd><a href="mailto:{{ $enquiry->email }}" class="text-slate-900 hover:underline break-all">{{ $enquiry->email }}</a></dd>
                        </div>
                        @if ($enquiry->phone)
                            <div>
                                <dt class="text-slate-500">Phone</dt>
                                <dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $enquiry->phone) }}" class="text-slate-900 hover:underline">{{ $enquiry->phone }}</a></dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-slate-500">Account</dt>
                            <dd class="text-slate-900">{{ $enquiry->sender?->name ?? 'Not signed in' }}</dd>
                        </div>
                    </dl>

                    <a href="mailto:{{ $enquiry->email }}?subject={{ rawurlencode('Re: '.$enquiry->subjectLine()) }}"
                       class="mt-4 inline-flex w-full justify-center rounded-md bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
                        Reply by email
                    </a>
                </x-card>

                @if ($enquiry->reservation_reference)
                    <x-card title="Booking quoted">
                        <p class="font-mono text-slate-900">{{ $enquiry->reservation_reference }}</p>

                        @if ($reservation)
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $reservation->guestName() }} &middot; room {{ $reservation->room->number }}<br>
                                {{ $reservation->starts_at->format('j M, H:i') }}–{{ $reservation->ends_at->format('H:i') }}
                            </p>
                            <a href="{{ route('staff.reservations.show', $reservation) }}"
                               class="mt-3 inline-flex w-full justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">
                                Open booking
                            </a>
                        @else
                            <p class="mt-1 text-sm text-amber-700">No booking matches that reference.</p>
                        @endif
                    </x-card>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
