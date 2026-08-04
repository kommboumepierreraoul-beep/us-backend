<?php

namespace App\Services;

use App\Models\Like;
use App\Models\Message;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Models\UserMatch;
use Illuminate\Database\Eloquent\Builder;

class ProfileCertificationService
{
    public function __construct(private readonly AdminNotificationService $adminNotifications) {}

    public function refresh(User $user): int
    {
        $profile = $user->profile;
        if (! $profile) {
            return 0;
        }

        $score = $this->score($user);
        $updates = ['certification_score' => $score];

        if ($score >= 100 && $profile->certification_status === 'not_eligible') {
            $updates['certification_status'] = 'eligible';
        }

        if ($score >= 100 && ! $profile->certification_notified_at && $profile->certification_status !== 'certified') {
            $updates['certification_notified_at'] = now();
            $this->adminNotifications->notify(
                'Profil eligible a la certification',
                ($profile->first_name ?? $user->email).' a atteint 100% de score de certification.',
                '/admin/certifications',
                ['user_id' => $user->id, 'profile_id' => $profile->id]
            );
        }

        $profile->update($updates);

        return $score;
    }

    public function certify(User $user): Profile
    {
        $profile = $user->profile()->firstOrFail();
        $profile->update([
            'certification_score' => 100,
            'certification_status' => 'certified',
            'certified_at' => now(),
        ]);

        return $profile->refresh();
    }

    public function score(User $user): int
    {
        $profile = $user->profile;
        if (! $profile) {
            return 0;
        }

        $completion = (int) ($profile->completion_score ?? 0);
        $approvedVerifications = $user->verificationRequests()->where('status', 'approved')->count();
        $photos = $user->photos()->count();
        $interactions = Like::query()->where('sender_id', $user->id)->orWhere('receiver_id', $user->id)->count()
            + Message::query()->where('sender_id', $user->id)->count()
            + UserMatch::query()->where(fn (Builder $q) => $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))->count();
        $reports = Report::query()->where('reported_user_id', $user->id)->count();

        return max(0, min(100,
            (int) round($completion * 0.45)
            + min(25, $approvedVerifications * 25)
            + min(20, $interactions * 2)
            + min(10, $photos * 2)
            - min(30, $reports * 10)
        ));
    }
}
