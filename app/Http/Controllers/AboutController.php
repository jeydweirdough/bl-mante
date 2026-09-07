<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomType;
use App\Support\SiteContent;
use App\Support\StructuredData;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    public function __construct(private readonly SiteContent $content) {}

    public function __invoke(): View
    {
        return view('public.about', [
            'c' => $this->content,

            'roomCount' => Room::where('is_bookable', true)->count(),
            'roomTypeCount' => RoomType::where('is_active', true)->count(),

            'title' => 'About us',
            'description' => sprintf(
                'Why %s sells rooms by the hour rather than by the night, how the turnover works, and what we do when plans change.',
                config('hotel.name'),
            ),
            'canonical' => route('about'),
            'noindex' => false,

            // The About page is where a search engine expects to find the
            // organisation described, so it carries the same Hotel entity as
            // the homepage rather than a thinner duplicate.
            'schema' => [
                StructuredData::hotel(),
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => url('/')],
                    ['name' => 'About', 'url' => route('about')],
                ]),
            ],
        ]);
    }
}
