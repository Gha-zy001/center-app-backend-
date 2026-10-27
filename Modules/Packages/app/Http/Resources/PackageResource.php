<?php

namespace Modules\Packages\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'durationDays' => $this->duration_days,
            'maxUsers' => $this->max_users,
            'isActive' => $this->is_active,
            'subscribersCount' => $this->whenCounted('subscriptions'),
            'createdAt' => $this->created_at?->toDateTimeString(),
        ];
    }
}
