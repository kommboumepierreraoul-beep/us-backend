<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $matchedUser = (int) $this->user_one_id === (int) $currentUserId ? $this->userTwo : $this->userOne;
        $profile = $matchedUser?->profile;
        if ($matchedUser && $profile) {
            $profile->setRelation('user', $matchedUser);
        }
        $myInterests = $request->user()?->profile?->interests?->pluck('name')->values() ?? collect();
        $theirInterests = $profile?->interests?->pluck('name')->values() ?? collect();
        $sharedInterests = $myInterests->intersect($theirInterests)->values();

        return [
            'id' => $this->id,
            'user_one_id' => $this->user_one_id,
            'user_two_id' => $this->user_two_id,
            'status' => $this->status,
            'matched_at' => $this->matched_at,
            'conversation' => $this->whenLoaded('conversation', fn () => new ConversationResource($this->conversation)),
            'matched_user' => $matchedUser ? [
                'id' => $matchedUser->id,
                'name' => $matchedUser->name,
                'status' => $matchedUser->status,
                'last_seen_at' => $matchedUser->last_seen_at,
                'profile' => $profile ? new ProfileResource($profile) : null,
            ] : null,
            'compatibility' => [
                'shared_interests' => $sharedInterests,
                'shared_interests_count' => $sharedInterests->count(),
                'same_university' => $profile?->university_id && $request->user()?->profile?->university_id
                    ? (int) $profile->university_id === (int) $request->user()->profile->university_id
                    : false,
                'score' => min(98, 55 + ($sharedInterests->count() * 10) + (
                    $profile?->university_id && $request->user()?->profile?->university_id && (int) $profile->university_id === (int) $request->user()->profile->university_id ? 15 : 0
                )),
                'explanation' => 'Score indicatif base sur les interets partages et le contexte academique.',
            ],
        ];
    }
}
