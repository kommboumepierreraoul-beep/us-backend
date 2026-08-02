<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'age' => $this->birth_date?->age,
            'gender' => $this->gender,
            'looking_for' => $this->looking_for,
            'bio' => $this->bio,
            'study_level' => $this->study_level,
            'languages' => $this->languages ?? [],
            'intentions' => $this->intentions ?? [],
            'visibility' => $this->visibility,
            'completion_score' => $this->completion_score,
            'university' => $this->whenLoaded('university', fn () => [
                'id' => $this->university?->id,
                'name' => $this->university?->name,
                'acronym' => $this->university?->acronym,
                'city' => $this->university?->city,
            ]),
            'interests' => $this->whenLoaded('interests', fn () => $this->interests->pluck('name')->values()),
            'photos' => $this->whenLoaded('user', fn () => $this->user->photos->map(fn ($photo) => [
                'id' => $photo->id,
                'url' => $photo->url,
                'is_primary' => $photo->is_primary,
                'moderation_status' => $photo->moderation_status,
            ])->values()),
        ];
    }
}
