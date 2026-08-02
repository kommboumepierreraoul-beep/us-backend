<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserNotification;

class AdminNotificationService
{
    public function notify(string $title, string $body, string $url, array $data = []): void
    {
        $admins = $this->adminUsers();

        foreach ($admins as $admin) {
            UserNotification::query()->create([
                'user_id' => $admin->id,
                'category' => 'admin_action',
                'title' => $title,
                'body' => $body,
                'data' => array_merge($data, ['url' => $url]),
            ]);
        }
    }

    private function adminUsers()
    {
        $emails = collect(explode(',', (string) env('ADMIN_EMAILS', '')))
            ->map(fn (string $email) => trim(strtolower($email)))
            ->filter()
            ->values();

        if ($emails->isNotEmpty()) {
            return User::query()->whereIn('email', $emails)->get();
        }

        return User::query()->whereKey(1)->get();
    }
}
