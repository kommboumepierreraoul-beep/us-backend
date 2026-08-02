<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'type' => $this->type,
            'body' => $this->body,
            'attachment_url' => $this->attachment_url,
            'sticker_code' => $this->sticker_code,
            'reply_to_message_id' => $this->reply_to_message_id,
            'reply_to_message' => $this->whenLoaded('replyTo', fn () => $this->replyTo ? [
                'id' => $this->replyTo->id,
                'sender_id' => $this->replyTo->sender_id,
                'type' => $this->replyTo->type,
                'body' => $this->replyTo->body,
                'attachment_url' => $this->replyTo->attachment_url,
                'sticker_code' => $this->replyTo->sticker_code,
            ] : null),
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
