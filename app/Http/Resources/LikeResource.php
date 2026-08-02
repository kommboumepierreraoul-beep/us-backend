<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LikeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $otherUser = (int) $this->sender_id === (int) $request->user()?->id ? $this->receiver : $this->sender;
        if ($otherUser?->profile) {
            $otherUser->profile->setRelation('user', $otherUser);
        }

        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->receiver_id,
            'type' => $this->type,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'other_user' => $otherUser ? [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'profile' => $otherUser->profile ? new ProfileResource($otherUser->profile) : null,
            ] : null,
        ];
    }
}
