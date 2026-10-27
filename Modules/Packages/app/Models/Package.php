<?php

namespace Modules\Packages\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = ['name', 'duration_days', 'is_active'];

    protected function casts(): array
    {
        return ['duration_days' => 'integer', 'is_active' => 'boolean'];
    }

    /** Adds users_count: the teachers whose running period (started, not yet ended) is on this package. */
    public function scopeWithUsersCount(Builder $query): void
    {
        $query->addSelect(['users_count' => TeacherSubscription::query()
            ->selectRaw('count(distinct teacher_id)')
            ->whereColumn('package_id', 'packages.id')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now()),
        ]);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TeacherSubscription::class);
    }
}
