<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', fn ($user, int $userId) => $user->id === $userId);

Broadcast::channel('conversation.{conversationId}', function ($user, int $conversationId) {
    return Conversation::query()
        ->whereKey($conversationId)
        ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
        ->exists();
});

Broadcast::channel('online', fn ($user) => ['id' => $user->id, 'name' => $user->name]);
