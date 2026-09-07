<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * The site chrome.
 *
 * The SEO fields are declared as constructor properties rather than left to
 * `$attributes`: a class-based component does not turn arbitrary attributes
 * into template variables, so `<x-app-layout :schema="...">` would silently
 * render nothing at all. Declaring them here is what makes them reachable in
 * layouts/app.blade.php.
 */
class AppLayout extends Component
{
    /**
     * @param  string|null  $title  Page title, before the site name is appended.
     * @param  string|null  $description  Meta description; falls back to the site default.
     * @param  string|null  $canonical  Canonical URL; defaults to the current one.
     * @param  bool|null  $noindex  Null means "decide from whether someone is signed in".
     * @param  array  $schema  One or more schema.org graphs, emitted as JSON-LD.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?bool $noindex = null,
        public array $schema = [],
    ) {}

    public function render(): View
    {
        return view('layouts.app');
    }
}
