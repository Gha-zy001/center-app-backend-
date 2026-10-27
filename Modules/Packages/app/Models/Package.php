<?php

namespace Modules\Packages\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = ['name', 'duration_days', 'max_users', 'is_active'];

    protected function casts(): array
    {
        return ['duration_days' => 'integer', 'max_users' => 'integer', 'is_active' => 'boolean'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TeacherSubscription::class);
    }
}
