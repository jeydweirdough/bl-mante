<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The front desk's inbox for contact-form messages.
 *
 * This exists so the form is not a black hole. A public contact form whose
 * messages nobody can read is worse than having no form at all: the guest
 * believes they have been in touch, and they have not.
 */
class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $enquiries = Enquiry::query()
            ->with('sender')
            ->when($request->string('filter')->toString() === 'unread', fn ($q) => $q->unread())
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = trim($request->string('q')->toString());

                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('subject', 'like', "%{$term}%")
                    ->orWhere('reservation_reference', 'like', "%{$term}%")
                    ->orWhere('message', 'like', "%{$term}%"));
            })
            // Unread first, then newest: the desk works a queue, not an archive.
            ->orderByRaw('CASE WHEN read_at IS NULL THEN 0 ELSE 1 END')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('staff.enquiries.index', [
            'enquiries' => $enquiries,
            'unreadCount' => Enquiry::unread()->count(),
            'title' => 'Enquiries',
        ]);
    }

    public function show(Request $request, Enquiry $enquiry): View
    {
        // Opening a message is what marks it read; there is no separate button
        // to forget to press.
        $enquiry->markRead($request->user());

        return view('staff.enquiries.show', [
            'enquiry' => $enquiry->load('sender', 'readBy'),
            'reservation' => $enquiry->reservation(),
            'title' => $enquiry->subjectLine(),
        ]);
    }

    /** Note what was done about it, and when it was answered. */
    public function update(Request $request, Enquiry $enquiry): RedirectResponse
    {
        $validated = $request->validate([
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'mark_replied' => ['nullable', 'boolean'],
        ]);

        $enquiry->forceFill([
            'internal_notes' => $validated['internal_notes'] ?? null,
            'replied_at' => $request->boolean('mark_replied') ? ($enquiry->replied_at ?? now()) : null,
        ])->save();

        return back()->with('status', 'Enquiry updated.');
    }
}
