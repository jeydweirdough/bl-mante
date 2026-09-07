<x-app-layout>
    <x-slot name="title">Enquiries</x-slot>

    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Enquiries</h1>
                <p class="mt-1 text-slate-600">Messages sent through the contact form on the website.</p>
            </div>
            @if ($unreadCount > 0)
                <x-badge classes="bg-amber-100 text-amber-800 ring-amber-600/20">{{ $unreadCount }} unread</x-badge>
            @endif
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-card>
            <form method="GET" class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-56">
                    <x-input-label for="q" value="Search" />
                    <input type="search" id="q" name="q" value="{{ request('q') }}"
                           placeholder="Name, email, subject or booking reference"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div>
                    <x-input-label for="filter" value="Show" />
                    <select id="filter" name="filter" class="mt-1 block rounded-md border-slate-300 shadow-sm">
                        <option value="">All</option>
                        <option value="unread" @selected(request('filter') === 'unread')>Unread only</option>
                    </select>
                </div>
                <button class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Filter</button>
                <a href="{{ route('staff.enquiries.index') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Reset</a>
            </form>
        </x-card>

        <x-card bodyClass="p-0">
            <ul class="divide-y divide-slate-100">
                @forelse ($enquiries as $enquiry)
                    <li class="{{ $enquiry->isUnread() ? 'bg-amber-50/40' : '' }}">
                        <a href="{{ route('staff.enquiries.show', $enquiry) }}" class="block px-5 py-4 hover:bg-slate-50">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-medium text-slate-900">
                                        @if ($enquiry->isUnread())
                                            <span class="inline-block h-2 w-2 rounded-full bg-amber-500 align-middle mr-1.5" aria-label="Unread"></span>
                                        @endif
                                        {{ $enquiry->subjectLine() }}
                                    </p>
                                    <p class="text-sm text-slate-600">
                                        {{ $enquiry->name }} &middot; {{ $enquiry->email }}
                                        @if ($enquiry->reservation_reference)
                                            &middot; <span class="font-mono">{{ $enquiry->reservation_reference }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-1 text-sm text-slate-500 line-clamp-2">{{ Str::limit($enquiry->message, 160) }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs text-slate-500">{{ $enquiry->created_at->diffForHumans() }}</p>
                                    @if ($enquiry->replied_at)
                                        <x-badge classes="bg-emerald-100 text-emerald-800 ring-emerald-600/20">Replied</x-badge>
                                    @endif
                                </div>
                            </div>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-12 text-center text-slate-500">No enquiries yet.</li>
                @endforelse
            </ul>
        </x-card>

        <div>{{ $enquiries->links() }}</div>
    </div>
</x-app-layout>
