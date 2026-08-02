<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\NotificationResource;
use App\Models\UserNotification;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        return $this->ok(NotificationResource::collection($request->user()->notifications()->latest()->paginate())->response()->getData(true));
    }

    public function unreadCount(Request $request)
    {
        return $this->ok(['count' => $request->user()->notifications()->whereNull('read_at')->count()]);
    }

    public function markRead(Request $request, UserNotification $notification)
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        return $this->ok(new NotificationResource($notification), 'Notification lue.');
    }

    public function markAllRead(Request $request)
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return $this->ok(null, 'Notifications lues.');
    }
}
