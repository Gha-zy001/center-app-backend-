<?php

namespace Modules\User\app\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid ?? $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar_url,
            'status' => $this->status,
            'emailVerified' => !is_null($this->email_verified_at),
            'lastLoginAt' => $this->last_login_at?->toDateTimeString(),
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'preferences' => $this->preferences,
            'roles' => $this->roles->pluck('name'),
            'createdAt' => $this->created_at?->toDateTimeString(),
            'updatedAt' => $this->updated_at?->toDateTimeString(),
        ];
    }
}