<x-app-layout>
    <x-slot name="title">Staff accounts</x-slot>

    <x-slot name="header">
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Staff and administrators</h1>
        <p class="mt-1 text-slate-600">
            Accounts are deactivated rather than deleted, so their attribution on past payments and actions keeps resolving.
        </p>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-card title="Add an account">
            <form method="POST" action="{{ route('admin.staff.store') }}" class="grid gap-3 sm:grid-cols-6">
                @csrf
                <div class="sm:col-span-2">
                    <x-input-label for="name" value="Name" />
                    <input type="text" id="name" name="name" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="email" value="Email" />
                    <input type="email" id="email" name="email" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div>
                    <x-input-label for="phone" value="Phone" />
                    <input type="text" id="phone" name="phone" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div>
                    <x-input-label for="role" value="Role" />
                    <select id="role" name="role" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-3">
                    <x-input-label for="password" value="Password" />
                    <input type="password" id="password" name="password" required autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div class="sm:col-span-3">
                    <x-input-label for="password_confirmation" value="Confirm password" />
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                           class="mt-1 block w-full rounded-md border-slate-300 shadow-sm">
                </div>
                <div class="sm:col-span-6">
                    <button class="rounded-md bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Create account</button>
                    <p class="mt-2 text-xs text-slate-500">
                        Passwords are stored as a one-way hash and cannot be read back by anyone, including administrators.
                    </p>
                </div>
            </form>
        </x-card>

        <div class="space-y-3">
            @foreach ($personnel as $person)
                <x-card>
                    <form method="POST" action="{{ route('admin.staff.update', $person) }}" class="grid gap-3 sm:grid-cols-6 items-end">
                        @csrf
                        @method('PUT')

                        <div class="sm:col-span-2">
                            <x-input-label value="Name" />
                            <input type="text" name="name" value="{{ $person->name }}"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Email" />
                            <input type="email" name="email" value="{{ $person->email }}"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        </div>
                        <div>
                            <x-input-label value="Role" />
                            <select name="role" @disabled($person->is(auth()->user()))
                                    class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm disabled:bg-slate-100">
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected($person->role === $role)>{{ $role->label() }}</option>
                                @endforeach
                            </select>
                            @if ($person->is(auth()->user()))
                                <input type="hidden" name="role" value="{{ $person->role->value }}">
                            @endif
                        </div>
                        <div>
                            <button class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-50">Save</button>
                        </div>

                        <div class="sm:col-span-3">
                            <x-input-label value="New password (optional)" />
                            <input type="password" name="password" autocomplete="new-password"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        </div>
                        <div class="sm:col-span-3">
                            <x-input-label value="Confirm new password" />
                            <input type="password" name="password_confirmation" autocomplete="new-password"
                                   class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm">
                        </div>
                    </form>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-3">
                        <div class="flex items-center gap-2">
                            @if ($person->is_active)
                                <x-badge classes="bg-emerald-100 text-emerald-800 ring-emerald-600/20">Active</x-badge>
                            @else
                                <x-badge classes="bg-neutral-200 text-neutral-700 ring-neutral-500/20">Deactivated</x-badge>
                            @endif
                            <span class="text-xs text-slate-500">
                                @if ($person->last_login_at)
                                    Last signed in {{ $person->last_login_at->diffForHumans() }}
                                @else
                                    Has not signed in yet
                                @endif
                            </span>
                        </div>

                        @can('deactivate', $person)
                            <form method="POST" action="{{ route('admin.staff.toggle', $person) }}">
                                @csrf
                                <button class="text-sm font-semibold {{ $person->is_active ? 'text-rose-700' : 'text-emerald-700' }} hover:underline">
                                    {{ $person->is_active ? 'Deactivate' : 'Reactivate' }}
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-slate-400">You cannot change your own role or deactivate yourself.</span>
                        @endcan
                    </div>
                </x-card>
            @endforeach
        </div>
    </div>
</x-app-layout>
