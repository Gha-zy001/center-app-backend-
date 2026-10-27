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
            'isActive' => $this->is_active,
            // Read-only: how many teachers are on this package right now.
            'usersCount' => $this->when(isset($this->users_count), fn () => (int) $this->users_count),
            'createdAt' => $this->created_at?->toDateTimeString(),
        ];
    }
}
