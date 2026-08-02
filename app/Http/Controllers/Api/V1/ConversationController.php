<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Models\Block;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\UserNotification;
use App\Services\ProfileCertificationService;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationController extends ApiController
{
    public function index(Request $request)
    {
        $conversations = Conversation::query()
            ->with([
                'participants',
                'latestMessage',
                'match.userOne.profile.university',
                'match.userOne.profile.interests',
                'match.userOne.photos',
                'match.userTwo.profile.university',
                'match.userTwo.profile.interests',
                'match.userTwo.photos',
            ])
            ->withCount(['unreadMessages' => fn ($q) => $q->where('sender_id', '!=', $request->user()->id)])
            ->whereHas('participants', fn ($q) => $q->where('user_id', $request->user()->id)->where('is_archived', false))
            ->latest('last_message_at')
            ->paginate();

        return $this->ok(ConversationResource::collection($conversations)->response()->getData(true));
    }

    public function unreadCount(Request $request)
    {
        $count = Message::query()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $request->user()->id)
            ->whereHas('conversation.participants', fn ($q) => $q->where('user_id', $request->user()->id)->where('is_archived', false))
            ->count();

        return $this->ok(['count' => $count]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->authorizeMember($request, $conversation);

        return $this->ok(MessageResource::collection(
            $conversation->messages()->with('replyTo')->latest()->paginate((int) $request->query('per_page', 30))
        )->response()->getData(true));
    }

    public function send(Request $request, Conversation $conversation, PushNotificationService $pushNotifications, ProfileCertificationService $certification)
    {
        $this->authorizeMember($request, $conversation);
        abort_if($conversation->status !== 'active', 403, 'Conversation fermee.');
        abort_if(! $this->canSendMessage($request, $conversation), 403, 'Limite du plan gratuit atteinte pour cette conversation.');

        $data = $request->validate([
            'type' => ['required', Rule::in(['text', 'image', 'sticker'])],
            'body' => ['required_if:type,text', 'nullable', 'string', 'max:2000'],
            'attachment_url' => ['required_if:type,image', 'nullable', 'url', 'max:2048'],
            'sticker_code' => ['required_if:type,sticker', 'nullable', 'string', 'max:80'],
            'reply_to_message_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

        if (! empty($data['reply_to_message_id'])) {
            abort_unless(
                Message::query()
                    ->whereKey($data['reply_to_message_id'])
                    ->where('conversation_id', $conversation->id)
                    ->exists(),
                422,
                'Le message cite ne fait pas partie de cette conversation.'
            );
        }

        $participantIds = $conversation->participants()->pluck('user_id');
        $recipientId = $participantIds->first(fn ($id) => (int) $id !== $request->user()->id);
        $blocked = Block::query()
            ->where(fn ($q) => $q->where('blocker_id', $request->user()->id)->where('blocked_id', $recipientId))
            ->orWhere(fn ($q) => $q->where('blocker_id', $recipientId)->where('blocked_id', $request->user()->id))
            ->exists();
        abort_if($blocked, 403, 'Conversation bloquee.');

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'type' => $data['type'],
            'body' => $data['body'] ?? null,
            'attachment_url' => $data['attachment_url'] ?? null,
            'sticker_code' => $data['sticker_code'] ?? null,
            'reply_to_message_id' => $data['reply_to_message_id'] ?? null,
        ]);
        $message->load('replyTo');
        $conversation->update(['last_message_at' => now()]);

        if ($recipientId) {
            $notification = UserNotification::query()->create([
                'user_id' => $recipientId,
                'category' => 'message',
                'title' => 'Nouveau message',
                'body' => match ($data['type']) {
                    'text' => str($data['body'] ?? '')->limit(120)->toString(),
                    'sticker' => 'Sticker recu',
                    default => 'Image recue',
                },
                'data' => ['conversation_id' => $conversation->id, 'message_id' => $message->id],
            ]);
            $recipient = $conversation->participants()->where('user_id', $recipientId)->first()?->user;
            if ($recipient) {
                $pushNotifications->sendToUser($recipient, $notification);
            }
        }
        $certification->refresh($request->user()->refresh());

        return $this->ok(new MessageResource($message), 'Message envoye.', status: 201);
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $this->authorizeMember($request, $conversation);
        $conversation->participants()->where('user_id', $request->user()->id)->update(['last_read_at' => now()]);
        $conversation->messages()->where('sender_id', '!=', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return $this->ok(null, 'Conversation marquee comme lue.');
    }

    private function authorizeMember(Request $request, Conversation $conversation): void
    {
        abort_unless($conversation->participants()->where('user_id', $request->user()->id)->exists(), 403);
    }

    private function canSendMessage(Request $request, Conversation $conversation): bool
    {
        $hasActiveSubscription = $request->user()->subscriptions()
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->exists();

        if ($hasActiveSubscription) {
            return true;
        }

        return $conversation->messages()->where('sender_id', $request->user()->id)->count() < 15;
    }
}
