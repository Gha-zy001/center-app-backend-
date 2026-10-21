<?php

namespace Modules\Chat\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversationId' => $this->conversation_id,
            'senderId' => $this->sender_id,
            'body' => $this->type === 'image' || $this->type === 'file' ? $this->getFirstMediaUrl('chat_media') : $this->body,
            'type' => $this->type,
            'sender' => $this->whenLoaded('sender', function () {
                return [
                    'id' => $this->sender->id,
                    'name' => $this->sender->name,
                ];
            }),
            'createdAt' => $this->created_at?->toDateTimeString(),
        ];
    }
}
