<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Block;
use App\Models\Message;
use App\Models\Photo;
use App\Models\Profile;
use App\Models\Report;
use App\Models\User;
use App\Services\AdminNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModerationController extends ApiController
{
    public function block(Request $request, User $user)
    {
        abort_if($request->user()->is($user), 422, 'Blocage impossible.');
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $block = Block::query()->updateOrCreate(
            ['blocker_id' => $request->user()->id, 'blocked_id' => $user->id],
            ['reason' => $data['reason'] ?? null]
        );

        return $this->ok($block, 'Utilisateur bloque.', status: 201);
    }

    public function unblock(Request $request, User $user)
    {
        Block::query()->where('blocker_id', $request->user()->id)->where('blocked_id', $user->id)->delete();

        return $this->ok(null, 'Utilisateur debloque.');
    }

    public function report(Request $request, AdminNotificationService $adminNotifications)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['profile', 'message', 'photo', 'user'])],
            'id' => ['required', 'integer'],
            'category' => ['required', Rule::in(['fake_profile', 'harassment', 'spam', 'scam', 'inappropriate_content', 'danger', 'other'])],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $model = match ($data['type']) {
            'profile' => Profile::class,
            'message' => Message::class,
            'photo' => Photo::class,
            default => User::class,
        };
        $target = $model::query()->findOrFail($data['id']);
        $reportedUserId = $target instanceof User ? $target->id : ($target->user_id ?? $target->sender_id ?? null);

        $report = Report::query()->create([
            'reporter_id' => $request->user()->id,
            'reported_user_id' => $reportedUserId,
            'reportable_type' => $model,
            'reportable_id' => $target->id,
            'category' => $data['category'],
            'details' => $data['details'] ?? null,
            'priority' => in_array($data['category'], ['danger', 'harassment'], true) ? 3 : 1,
        ]);

        $adminNotifications->notify(
            'Nouveau signalement',
            'Un signalement '.$data['category'].' attend une action de moderation.',
            '/admin/reports',
            ['report_id' => $report->id, 'priority' => $report->priority]
        );

        return $this->ok($report, 'Signalement enregistre.', status: 201);
    }
}
