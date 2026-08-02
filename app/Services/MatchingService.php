<?php

namespace App\Services;

use App\Models\Block;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Like;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchingService
{
    public function __construct(private readonly PushNotificationService $pushNotifications) {}

    public function like(User $sender, User $receiver, string $type = 'like'): array
    {
        if ($sender->is($receiver) || $receiver->status !== 'active') {
            throw ValidationException::withMessages(['receiver_id' => 'Profil indisponible.']);
        }

        $blocked = Block::query()
            ->where(fn ($q) => $q->where('blocker_id', $sender->id)->where('blocked_id', $receiver->id))
            ->orWhere(fn ($q) => $q->where('blocker_id', $receiver->id)->where('blocked_id', $sender->id))
            ->exists();

        if ($blocked) {
            throw ValidationException::withMessages(['receiver_id' => 'Interaction impossible avec ce profil.']);
        }

        return DB::transaction(function () use ($sender, $receiver, $type) {
            $like = Like::query()->updateOrCreate(
                ['sender_id' => $sender->id, 'receiver_id' => $receiver->id],
                ['type' => $type, 'status' => 'active']
            );

            $reciprocal = Like::query()
                ->where('sender_id', $receiver->id)
                ->where('receiver_id', $sender->id)
                ->where('status', 'active')
                ->exists();

            if (! $reciprocal) {
                return ['like' => $like, 'match' => null, 'conversation' => null];
            }

            [$one, $two] = $sender->id < $receiver->id ? [$sender, $receiver] : [$receiver, $sender];
            $match = UserMatch::query()->firstOrCreate(
                ['user_one_id' => $one->id, 'user_two_id' => $two->id],
                ['status' => 'active', 'matched_at' => now()]
            );

            $conversation = Conversation::query()->firstOrCreate(
                ['match_id' => $match->id],
                ['status' => 'active']
            );

            foreach ([$sender, $receiver] as $member) {
                ConversationParticipant::query()->firstOrCreate([
                    'conversation_id' => $conversation->id,
                    'user_id' => $member->id,
                ]);

                $notification = UserNotification::query()->create([
                    'user_id' => $member->id,
                    'category' => 'match',
                    'title' => 'Nouveau match',
                    'body' => 'Vous avez un nouveau match.',
                    'data' => ['match_id' => $match->id, 'conversation_id' => $conversation->id],
                ]);
                $this->pushNotifications->sendToUser($member, $notification);
            }

            return ['like' => $like, 'match' => $match, 'conversation' => $conversation];
        });
    }
}
