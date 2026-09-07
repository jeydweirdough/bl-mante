<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEnquiryRequest;
use App\Models\Enquiry;
use App\Support\SiteContent;
use App\Support\StructuredData;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ContactController extends Controller
{
    public function __construct(private readonly SiteContent $content) {}

    public function show(): View
    {
        return view('public.contact', [
            'c' => $this->content,

            'title' => 'Contact us',
            'description' => sprintf(
                'Call %s on %s, email us, or send a message. Reception is staffed 24 hours at %s, %s.',
                config('hotel.name'),
                config('hotel.contact_phone'),
                config('hotel.address.street'),
                config('hotel.address.district'),
            ),
            'canonical' => route('contact'),
            'noindex' => false,

            'schema' => [
                StructuredData::contactPage(),
                StructuredData::breadcrumbs([
                    ['name' => 'Home', 'url' => url('/')],
                    ['name' => 'Contact', 'url' => route('contact')],
                ]),
            ],
        ]);
    }

    /**
     * Store the message and confirm it.
     *
     * Persisted rather than emailed: a form whose only copy goes to an SMTP
     * server loses the message when that server is unreachable, and this
     * system has no mail transport configured. The front desk reads these from
     * an inbox screen.
     *
     * Redirect on success rather than rendering, so a refresh cannot resubmit.
     */
    public function store(StoreEnquiryRequest $request): RedirectResponse
    {
        $enquiry = Enquiry::create($request->toEnquiry());

        return redirect()
            ->route('contact')
            ->with('status', $this->content->get('contact.success'))
            ->with('enquiry_reference', $enquiry->id);
    }
}
