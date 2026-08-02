<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Like;
use App\Models\Profile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DiscoveryService
{
    public function candidatesFor(User $user, array $filters = []): LengthAwarePaginator
    {
        $profile = $user->profile;
        $preferences = $user->discoveryPreference;
        $blockedIds = Block::query()
            ->where('blocker_id', $user->id)
            ->orWhere('blocked_id', $user->id)
            ->get()
            ->flatMap(fn ($block) => [$block->blocker_id, $block->blocked_id])
            ->unique()
            ->all();

        $alreadySeenIds = Like::query()
            ->where('sender_id', $user->id)
            ->pluck('receiver_id')
            ->all();

        $query = Profile::query()
            ->with(['user.photos', 'university', 'interests'])
            ->where('user_id', '!=', $user->id)
            ->where('visibility', 'visible')
            ->whereNotIn('user_id', array_merge($blockedIds, $alreadySeenIds))
            ->whereHas('user', fn ($q) => $q->where('status', 'active'))
            ->whereHas('user.photos', null, '>=', 2);

        $minAge = $filters['min_age'] ?? $preferences?->min_age;
        $maxAge = $filters['max_age'] ?? $preferences?->max_age;
        if ($minAge) {
            $query->whereDate('birth_date', '<=', now()->subYears((int) $minAge)->toDateString());
        }
        if ($maxAge) {
            $query->whereDate('birth_date', '>=', now()->subYears((int) $maxAge + 1)->addDay()->toDateString());
        }

        $gender = match ($profile?->gender) {
            'homme', 'man' => 'femme',
            'femme', 'woman' => 'homme',
            default => $preferences?->gender,
        };
        if ($gender) {
            $query->where('gender', $gender);
        }

        if (($filters['same_university_only'] ?? $preferences?->same_university_only) && $profile?->university_id) {
            $query->where('university_id', $profile->university_id);
        }

        return $query
            ->orderByDesc('completion_score')
            ->orderByDesc('updated_at')
            ->paginate((int) ($filters['per_page'] ?? 20));
    }

    public function distanceLabel(?CarbonInterface $lastSeenAt = null): string
    {
        if (! $lastSeenAt) {
            return 'proche';
        }

        return $lastSeenAt->greaterThan(now()->subMinutes(15)) ? 'en ligne' : 'actif recemment';
    }
}
