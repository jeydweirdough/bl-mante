@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'image' => null,
    'noindex' => false,
    'schema' => [],
])

@php
    $siteName = config('hotel.name');

    // The authenticated pages still set their heading with
    // <x-slot name="title">, which arrives here as a ComponentSlot rather than
    // a string. Coerce and trim so an empty slot is treated as absent instead
    // of producing a title that is just the separator.
    $title = filled(trim((string) $title)) ? trim((string) $title) : null;
    $description = filled(trim((string) $description)) ? trim((string) $description) : null;

    // Kept under ~60 characters where possible: that is roughly what Google
    // renders before truncating. The site name is appended rather than led
    // with, so the distinctive words come first.
    $fullTitle = $title
        ? $title.' | '.$siteName
        : $siteName.' — '.config('hotel.seo.tagline');

    $metaDescription = $description ?: config('hotel.seo.default_description');

    // Self-referencing canonical by default. Without one, the same page
    // reachable with tracking parameters looks like duplicate content.
    $canonicalUrl = $canonical ?: url()->current();

    $socialImage = $image
        ?: asset(config('hotel.seo.social_image'));

    // A single schema graph or several; both are allowed, and one <script>
    // per top-level object is the simplest thing that validates.
    $schemas = array_values(array_filter(is_array($schema) && array_is_list($schema) ? $schema : [$schema]));
@endphp

<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ Str::limit(strip_tags($metaDescription), 158, '') }}">
<link rel="canonical" href="{{ $canonicalUrl }}">

@if ($noindex)
    {{-- Booking flows, account pages and anything behind a login must never be
         indexed: they are duplicate, personal, or both. --}}
    <meta name="robots" content="noindex, nofollow">
@else
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
@endif

{{-- Open Graph, used by Facebook, LinkedIn, WhatsApp and most chat apps. --}}
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ Str::limit(strip_tags($metaDescription), 158, '') }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:image" content="{{ $socialImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:locale" content="en_PH">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ Str::limit(strip_tags($metaDescription), 158, '') }}">
<meta name="twitter:image" content="{{ $socialImage }}">

{{-- Helps a phone offer "call" and "directions" straight from a search result. --}}
<meta name="geo.position" content="{{ config('hotel.geo.latitude') }};{{ config('hotel.geo.longitude') }}">
<meta name="geo.placename" content="{{ config('hotel.address.district') }}, {{ config('hotel.address.city') }}">
<meta name="geo.region" content="{{ config('hotel.address.country') }}">
<meta name="theme-color" content="#0f172a">

@foreach ($schemas as $graph)
    <script type="application/ld+json">{!! json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
