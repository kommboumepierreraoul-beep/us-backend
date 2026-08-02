<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'title' => $this->title,
            'body' => $this->body,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'data' => $this->data ?? [],
            'action_url' => $this->actionUrl(),
        ];
    }

    private function actionUrl(): string
    {
        $data = $this->data ?? [];
        if ($this->category === 'message' && isset($data['conversation_id'])) {
            return '/dashboard/messages?conversation='.$data['conversation_id'];
        }
        if ($this->category === 'match') {
            return '/dashboard/matches';
        }
        if ($this->category === 'verification') {
            return '/dashboard/verification';
        }
        if ($this->category === 'premium') {
            return '/dashboard/premium';
        }

        return '/dashboard/notifications';
    }
}
