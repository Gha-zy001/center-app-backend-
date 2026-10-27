<?php

namespace Modules\Packages\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Teachers\app\Models\Teacher;

class TeacherSubscription extends Model
{
    protected $fillable = ['teacher_id', 'package_id', 'starts_at', 'ends_at', 'expired_handled_at'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'expired_handled_at' => 'datetime'];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class)->withTrashed();
    }

    public function isExpired(): bool
    {
        return $this->ends_at->lte(now());
    }
}
