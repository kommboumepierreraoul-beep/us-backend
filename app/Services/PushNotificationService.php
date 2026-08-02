<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    public function sendToUser(User $user, UserNotification $notification): void
    {
        $publicKey = config('services.webpush.vapid_public_key');
        $privateKey = config('services.webpush.vapid_private_key');

        if (! $publicKey || ! $privateKey) {
            return;
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $payload = json_encode([
            'title' => $notification->title,
            'body' => $notification->body,
            'url' => $this->targetUrl($notification),
            'category' => $notification->category,
            'notification_id' => $notification->id,
        ]);

        $user->pushSubscriptions()->get()->each(function (PushSubscription $pushSubscription) use ($webPush, $payload) {
            $subscription = Subscription::create([
                'endpoint' => $pushSubscription->endpoint,
                'publicKey' => $pushSubscription->public_key,
                'authToken' => $pushSubscription->auth_token,
                'contentEncoding' => $pushSubscription->content_encoding,
            ]);

            $webPush->queueNotification($subscription, $payload);
        });

        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            if ($report->isSuccess()) {
                PushSubscription::query()->where('endpoint', $endpoint)->update(['last_used_at' => now()]);
            } elseif ($report->isSubscriptionExpired()) {
                PushSubscription::query()->where('endpoint', $endpoint)->delete();
            }
        }
    }

    private function targetUrl(UserNotification $notification): string
    {
        $data = $notification->data ?? [];
        if (($notification->category === 'message') && isset($data['conversation_id'])) {
            return '/dashboard/messages?conversation='.$data['conversation_id'];
        }
        if ($notification->category === 'match') {
            return '/dashboard/matches';
        }

        return '/dashboard/notifications';
    }
}
