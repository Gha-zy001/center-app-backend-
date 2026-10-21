<?php

namespace Modules\Teachers\app\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeacherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'phone' => $this->phone,
            'status' => $this->status,
            'isActive' => $this->isActive(),
            'lastLoginAt' => $this->last_login_at?->toDateTimeString(),
            'createdAt' => $this->created_at?->toDateTimeString(),
        ];
    }
}
