<?php

namespace Modules\Chat\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'participants' => ParticipantResource::collection($this->whenLoaded('participants')),
            'lastMessage' => $this->relationLoaded('messages') && $this->messages->isNotEmpty() 
                ? new MessageResource($this->messages->first()) 
                : null,
            'createdAt' => $this->created_at?->toDateTimeString(),
            'updatedAt' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
