<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\SupportTicket;
use App\Services\AdminNotificationService;
use App\Services\CloudinaryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportTicketController extends ApiController
{
    public function index(Request $request)
    {
        return $this->ok(
            $request->user()
                ->supportTickets()
                ->latest()
                ->paginate((int) $request->query('per_page', 15))
        );
    }

    public function store(Request $request, CloudinaryService $cloudinary, AdminNotificationService $adminNotifications)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(['account', 'payment', 'safety', 'verification', 'bug', 'general'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        if ($request->hasFile('attachment')) {
            $upload = $cloudinary->uploadSupportAttachment($request->file('attachment'));
            $data['attachment_url'] = $upload['url'];
            $data['cloudinary_public_id'] = $upload['public_id'] ?? null;
        }

        unset($data['attachment']);
        $ticket = SupportTicket::query()->create([
            ...$data,
            'user_id' => $request->user()->id,
            'status' => 'open',
        ]);

        $adminNotifications->notify(
            'Nouveau ticket support',
            "{$request->user()->email} a ouvert: {$ticket->subject}",
            '/admin/support',
            ['ticket_id' => $ticket->id, 'priority' => $ticket->priority]
        );

        return $this->ok($ticket, 'Ticket support envoye.', status: 201);
    }
}
