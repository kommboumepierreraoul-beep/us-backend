<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $participant = $this->participants?->firstWhere('user_id', $currentUserId);
        $matchedUser = null;
        if ($this->relationLoaded('match') && $this->match) {
            $matchedUser = (int) $this->match->user_one_id === (int) $currentUserId ? $this->match->userTwo : $this->match->userOne;
            if ($matchedUser?->profile) {
                $matchedUser->profile->setRelation('user', $matchedUser);
            }
        }

        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'status' => $this->status,
            'last_message_at' => $this->last_message_at,
            'unread_count' => $this->whenCounted('unreadMessages'),
            'latest_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? new MessageResource($this->latestMessage) : null),
            'matched_user' => $matchedUser ? [
                'id' => $matchedUser->id,
                'name' => $matchedUser->name,
                'profile' => $matchedUser->profile ? new ProfileResource($matchedUser->profile) : null,
            ] : null,
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($participant) => [
                'user_id' => $participant->user_id,
                'last_read_at' => $participant->last_read_at,
            ])),
        ];
    }
}
