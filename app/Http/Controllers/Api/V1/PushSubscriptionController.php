<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\UserNotification;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;

class PushSubscriptionController extends ApiController
{
    public function publicKey()
    {
        return $this->ok(['public_key' => config('services.webpush.vapid_public_key')]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'content_encoding' => ['nullable', 'string', 'max:30'],
        ]);

        $subscription = $request->user()->pushSubscriptions()->updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
                'user_agent' => $request->userAgent(),
            ]
        );

        return $this->ok($subscription, 'Notifications push activees.', status: 201);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:2048']]);
        $request->user()->pushSubscriptions()->where('endpoint', $data['endpoint'])->delete();

        return $this->ok(null, 'Notifications push desactivees.');
    }

    public function test(Request $request, PushNotificationService $push)
    {
        $notification = UserNotification::query()->create([
            'user_id' => $request->user()->id,
            'category' => 'system',
            'title' => 'Notifications activees',
            'body' => 'US pourra maintenant vous prevenir pour les matchs et messages.',
            'data' => ['url' => '/dashboard/notifications'],
        ]);

        $push->sendToUser($request->user(), $notification);

        return $this->ok($notification, 'Notification test envoyee.');
    }
}
