<?php

namespace Modules\Packages\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expired = $this->isExpired();

        return [
            'id' => $this->id,
            'teacherId' => $this->teacher_id,
            'package' => $this->whenLoaded('package', fn () => new PackageResource($this->package)),
            'teacher' => $this->whenLoaded('teacher', fn () => ['id' => $this->teacher->id, 'name' => $this->teacher->name, 'username' => $this->teacher->username, 'status' => $this->teacher->status]),
            'startsAt' => $this->starts_at->toIso8601String(),
            'endsAt' => $this->ends_at->toIso8601String(),
            'status' => $expired ? 'expired' : 'active',
            'daysLeft' => $expired ? 0 : (int) ceil(now()->diffInSeconds($this->ends_at, false) / 86400),
        ];
    }
}
