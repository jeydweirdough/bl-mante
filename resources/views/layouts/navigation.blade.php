@php
    $user = auth()->user();
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-slate-200 sticky top-0 z-30">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <a href="{{ route('home') }}" class="shrink-0 flex items-center gap-2">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-900 text-white font-semibold">BM</span>
                    <span class="font-semibold text-slate-900 hidden sm:block">{{ config('hotel.name') }}</span>
                </a>

                <div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('rooms.index')" :active="request()->routeIs('rooms.*')">Rooms</x-nav-link>
                    <x-nav-link :href="route('availability')" :active="request()->routeIs('availability')">Check availability</x-nav-link>

                    @auth
                        @if ($user->isPersonnel())
                            <x-nav-link :href="route('staff.dashboard')" :active="request()->routeIs('staff.dashboard')">Front desk</x-nav-link>
                            <x-nav-link :href="route('staff.reservations.index')" :active="request()->routeIs('staff.reservations.*')">Bookings</x-nav-link>
                            <x-nav-link :href="route('staff.housekeeping')" :active="request()->routeIs('staff.housekeeping')">Housekeeping</x-nav-link>
                        @else
                            <x-nav-link :href="route('reservations.index')" :active="request()->routeIs('reservations.*')">My bookings</x-nav-link>
                        @endif

                        @can('access-admin-area')
                            <x-nav-link :href="route('admin.reports.index')" :active="request()->routeIs('admin.*')">Admin</x-nav-link>
                        @endcan
                    @endauth
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-3">
                @guest
                    <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Sign in</a>
                    <a href="{{ route('register') }}" class="inline-flex items-center rounded-md bg-slate-900 px-3.5 py-2 text-sm font-semibold text-white hover:bg-slate-700">Create account</a>
                @endguest

                @auth
                    <x-dropdown align="right" width="56">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-600 hover:text-slate-900 focus:outline-none">
                                <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700">
                                    {{ Str::of($user->name)->explode(' ')->take(2)->map(fn ($p) => Str::substr($p, 0, 1))->implode('') }}
                                </span>
                                <span class="hidden lg:block">{{ $user->name }}</span>
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <div class="px-4 py-2 border-b border-slate-100">
                                <p class="text-sm font-medium text-slate-900">{{ $user->name }}</p>
                                <p class="text-xs text-slate-500">{{ $user->role->label() }}</p>
                            </div>

                            @if ($user->isPersonnel())
                                <x-dropdown-link :href="route('staff.dashboard')">Front desk</x-dropdown-link>
                            @else
                                <x-dropdown-link :href="route('reservations.index')">My bookings</x-dropdown-link>
                            @endif

                            @can('access-admin-area')
                                <div class="border-t border-slate-100 my-1"></div>
                                <x-dropdown-link :href="route('admin.room-types.index')">Room types</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.rooms.index')">Rooms</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.pricing.index')">Pricing</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.extras.index')">Extras</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.policy.edit')">Booking policy</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.staff.index')">Staff accounts</x-dropdown-link>
                                <x-dropdown-link :href="route('admin.reports.index')">Reports</x-dropdown-link>
                            @endcan

                            <div class="border-t border-slate-100 my-1"></div>
                            <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault(); this.closest('form').submit();">
                                    Sign out
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @endauth
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-slate-500 hover:bg-slate-100 focus:outline-none">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-slate-200">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('rooms.index')" :active="request()->routeIs('rooms.*')">Rooms</x-responsive-nav-link>
            <x-responsive-nav-link :href="route('availability')" :active="request()->routeIs('availability')">Check availability</x-responsive-nav-link>

            @auth
                @if ($user->isPersonnel())
                    <x-responsive-nav-link :href="route('staff.dashboard')">Front desk</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('staff.reservations.index')">Bookings</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('staff.housekeeping')">Housekeeping</x-responsive-nav-link>
                @else
                    <x-responsive-nav-link :href="route('reservations.index')">My bookings</x-responsive-nav-link>
                @endif

                @can('access-admin-area')
                    <x-responsive-nav-link :href="route('admin.reports.index')">Admin</x-responsive-nav-link>
                @endcan
            @endauth
        </div>

        <div class="pt-4 pb-3 border-t border-slate-200">
            @auth
                <div class="px-4">
                    <div class="font-medium text-base text-slate-800">{{ $user->name }}</div>
                    <div class="font-medium text-sm text-slate-500">{{ $user->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.edit')">Profile</x-responsive-nav-link>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                            Sign out
                        </x-responsive-nav-link>
                    </form>
                </div>
            @endauth

            @guest
                <div class="space-y-1">
                    <x-responsive-nav-link :href="route('login')">Sign in</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('register')">Create account</x-responsive-nav-link>
                </div>
            @endguest
        </div>
    </div>
</nav>
