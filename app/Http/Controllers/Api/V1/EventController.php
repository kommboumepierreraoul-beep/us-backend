<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Event;
use App\Models\EventInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventController extends ApiController
{
    public function index(Request $request)
    {
        $events = Event::query()
            ->with('images')
            ->when($request->query('category'), fn ($query, $category) => $query->where('category', $category))
            ->whereIn('status', ['open', 'waitlist', 'full'])
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at')
            ->paginate((int) $request->query('per_page', 20));

        return $this->ok($events);
    }

    public function show(Event $event)
    {
        return $this->ok($event->load('images')->loadCount([
            'invitations as confirmed_count' => fn ($query) => $query->where('status', 'accepted'),
        ]));
    }

    public function invitations(Request $request)
    {
        return $this->ok(
            $request->user()
                ->eventInvitations()
                ->with('event')
                ->latest()
                ->paginate((int) $request->query('per_page', 20))
        );
    }

    public function respond(Request $request, EventInvitation $invitation)
    {
        abort_unless($invitation->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['accepted', 'declined'])],
        ]);

        $invitation->update([
            'status' => $data['status'],
            'responded_at' => now(),
            'ticket_code' => $data['status'] === 'accepted'
                ? ($invitation->ticket_code ?: 'US-'.Str::upper(Str::random(8)))
                : null,
        ]);

        return $this->ok($invitation->load('event'), 'Invitation mise a jour.');
    }

    public function ticket(Request $request, EventInvitation $invitation)
    {
        abort_unless($invitation->user_id === $request->user()->id, 403);
        abort_unless($invitation->status === 'accepted', 404);

        return $this->ok($invitation->load('event'));
    }
}
