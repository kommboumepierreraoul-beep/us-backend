<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\LikeResource;
use App\Http\Resources\MatchResource;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Like;
use App\Models\User;
use App\Models\UserMatch;
use App\Services\MatchingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MatchingController extends ApiController
{
    public function like(Request $request, MatchingService $matching)
    {
        $data = $request->validate([
            'receiver_id' => ['required', 'exists:users,id'],
            'type' => ['nullable', Rule::in(['like', 'super_like'])],
        ]);

        $result = $matching->like($request->user(), User::findOrFail($data['receiver_id']), $data['type'] ?? 'like');

        return $this->ok($result, $result['match'] ? 'Match cree.' : 'Like enregistre.', status: 201);
    }

    public function likes(Request $request)
    {
        $direction = $request->query('direction', 'received');
        $query = Like::query()
            ->with([
                'sender.profile.university',
                'sender.profile.interests',
                'sender.photos',
                'receiver.profile.university',
                'receiver.profile.interests',
                'receiver.photos',
            ])
            ->where('status', 'active')
            ->latest();

        if ($direction === 'sent') {
            $query->where('sender_id', $request->user()->id);
        } else {
            $query->where('receiver_id', $request->user()->id);
        }

        return $this->ok(LikeResource::collection($query->paginate())->response()->getData(true));
    }

    public function matches(Request $request)
    {
        UserMatch::query()
            ->where(fn ($q) => $q->where('user_one_id', $request->user()->id)->orWhere('user_two_id', $request->user()->id))
            ->where('status', 'active')
            ->doesntHave('conversation')
            ->get()
            ->each(function (UserMatch $match) {
                $conversation = Conversation::query()->create([
                    'match_id' => $match->id,
                    'status' => 'active',
                ]);
                foreach ([$match->user_one_id, $match->user_two_id] as $userId) {
                    ConversationParticipant::query()->firstOrCreate([
                        'conversation_id' => $conversation->id,
                        'user_id' => $userId,
                    ]);
                }
            });

        $matches = UserMatch::query()
            ->with([
                'conversation.participants',
                'userOne.profile.university',
                'userOne.profile.interests',
                'userOne.photos',
                'userTwo.profile.university',
                'userTwo.profile.interests',
                'userTwo.photos',
            ])
            ->where(fn ($q) => $q->where('user_one_id', $request->user()->id)->orWhere('user_two_id', $request->user()->id))
            ->where('status', 'active')
            ->latest('matched_at')
            ->paginate();

        return $this->ok(MatchResource::collection($matches)->response()->getData(true));
    }

    public function unmatch(Request $request, UserMatch $match)
    {
        abort_unless(in_array($request->user()->id, [$match->user_one_id, $match->user_two_id], true), 403);
        $match->update(['status' => 'unmatched']);
        $match->conversation?->update(['status' => 'closed']);

        return $this->ok(null, 'Match ferme.');
    }
}
