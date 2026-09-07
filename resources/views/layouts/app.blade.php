<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Titles, description, canonical, social cards and JSON-LD all come
             from one component so no page can quietly ship without them.
             Anything behind a login passes :noindex so private pages and
             duplicate booking-flow URLs stay out of the index. --}}
        <x-seo
            :title="$title"
            :description="$description"
            :canonical="$canonical"
            :noindex="$noindex ?? auth()->check()"
            :schema="$schema" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased h-full bg-slate-50 text-slate-900">
        <div class="min-h-full flex flex-col">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white border-b border-slate-200">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main class="flex-1">
                {{-- Flash messages. `status` is good news, `unavailable` is the
                     domain refusal channel -- a room taken, an extension that
                     will not fit -- which is a normal outcome, not an error. --}}
                @if (session('status') || session('unavailable') || $errors->any())
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-3">
                        @if (session('status'))
                            <x-alert tone="success">{{ session('status') }}</x-alert>
                        @endif

                        @if (session('unavailable'))
                            <x-alert tone="warning">{{ session('unavailable') }}</x-alert>
                        @endif

                        @if ($errors->any())
                            <x-alert tone="error">
                                <ul class="list-disc list-inside space-y-1">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </x-alert>
                        @endif
                    </div>
                @endif

                {{ $slot }}
            </main>

            <footer class="border-t border-slate-200 bg-white mt-16">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 text-sm text-slate-500 sm:flex sm:justify-between">
                    <p>&copy; {{ now()->year }} {{ config('hotel.name') }}. All times shown are property local time.</p>
                    <p class="mt-2 sm:mt-0">
                        {{ config('hotel.contact_phone') }} &middot;
                        <a href="mailto:{{ config('hotel.contact_email') }}" class="hover:text-slate-800">{{ config('hotel.contact_email') }}</a>
                    </p>
                </div>
            </footer>
        </div>

        {{-- Page scripts. These are plain <script> tags rather than modules, so
             they run during parsing and are defined before Alpine (loaded as a
             deferred module by Vite) initialises and looks for them. --}}
        @stack('scripts')
    </body>
</html>
