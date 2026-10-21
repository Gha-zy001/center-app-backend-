<?php

namespace Modules\UserNotifications\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserNotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'isRead' => $this->read_at !== null,
            'createdAt' => $this->created_at->toDateTimeString(),
        ];
    }
}
