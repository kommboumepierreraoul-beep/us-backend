<?php

namespace App\Services;

use App\Models\Profile;

class ProfileCompletionService
{
    public function score(Profile $profile): int
    {
        $checks = [
            filled($profile->first_name),
            filled($profile->birth_date),
            filled($profile->gender),
            filled($profile->bio),
            filled($profile->study_level),
            filled($profile->university_id),
            count($profile->languages ?? []) > 0,
            count($profile->intentions ?? []) > 0,
            $profile->interests()->exists(),
            ($profile->user?->photos()->count() ?? 0) >= 2,
        ];

        return (int) round((count(array_filter($checks)) / count($checks)) * 100);
    }
}
