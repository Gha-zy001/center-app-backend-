<?php

namespace Modules\Packages\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\Packages\app\Models\Package;
use Modules\Packages\app\Models\TeacherSubscription;
use Modules\Teachers\app\Contracts\TeacherAccessGate;
use Modules\Teachers\app\Models\Teacher;

/**
 * Which package a teacher is on and what happens when it runs out.
 *
 * A teacher with no subscription at all is left alone (accounts that predate packages keep working);
 * the account is only locked once a subscription exists and its end date has passed.
 */
class SubscriptionService implements TeacherAccessGate
{
    public function current(Teacher $teacher): ?TeacherSubscription
    {
        return TeacherSubscription::with('package')
            ->where('teacher_id', $teacher->id)
            ->orderByDesc('ends_at')->orderByDesc('id')
            ->first();
    }

    // Used on every teacher request: the lockout does not wait for the hourly job.
    public function denial(Teacher $teacher): ?string
    {
        $current = $this->current($teacher);

        return $current && $current->isExpired() ? 'messages.subscription_expired' : null;
    }

    // Hours before the end of a package at which the teacher starts seeing the warning.
    public const WARN_HOURS = 48;

    // "Your package ends in less than 48 hours": null when it ends later, has ended, or there is no package.
    public function notice(Teacher $teacher): ?array
    {
        $current = $this->current($teacher);

        if (! $current || $current->isExpired() || $current->ends_at->gt(now()->addHours(self::WARN_HOURS))) {
            return null;
        }

        return [
            'code' => 'subscription_expiring',
            'message' => __('messages.subscription_expiring'),
            'endsAt' => $current->ends_at->toIso8601String(),
            'hoursLeft' => max(1, (int) ceil(now()->diffInSeconds($current->ends_at, false) / 3600)),
        ];
    }

    /**
     * Starts a package for a teacher. By default it starts now and replaces the running one; with
     * $extend it begins when the running one ends, so no paid days are lost.
     */
    public function assign(Teacher $teacher, Package $package, bool $extend = false): TeacherSubscription
    {
        return DB::transaction(function () use ($teacher, $package, $extend) {
            $current = $this->current($teacher);
            // Queuing after the running period only makes sense when renewing the same package.
            // Switching to a different one always starts now, whatever the client asked for.
            $extend = $extend && $current && $current->package_id === $package->id;
            $running = $current && ! $current->isExpired();
            $startsAt = $extend && $running ? $current->ends_at->copy() : now();

            // Replacing closes every period still running (an earlier extension may be queued behind it).
            if (! $extend) {
                TeacherSubscription::where('teacher_id', $teacher->id)->where('ends_at', '>', now())->update(['ends_at' => now()]);
            }

            $subscription = TeacherSubscription::create([
                'teacher_id' => $teacher->id,
                'package_id' => $package->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addDays($package->duration_days),
            ]);

            // Lift a suspension this module caused; one the admin set by hand is left alone.
            if ($current?->expired_handled_at && $teacher->status === Teacher::STATUS_SUSPENDED) {
                $teacher->update(['status' => Teacher::STATUS_ACTIVE]);
            }

            return $subscription->load('package');
        });
    }

    /**
     * Suspends every teacher whose latest subscription has ended and signs them out.
     *
     * @return int how many accounts were suspended
     */
    public function expireDue(): int
    {
        $count = 0;

        TeacherSubscription::query()
            ->whereNull('expired_handled_at')
            ->where('ends_at', '<=', now())
            // Only the newest period of each teacher counts: an older one ending is no news.
            ->whereNotExists(fn ($newer) => $newer->selectRaw('1')->from('teacher_subscriptions as next')
                ->whereColumn('next.teacher_id', 'teacher_subscriptions.teacher_id')
                ->whereColumn('next.ends_at', '>', 'teacher_subscriptions.ends_at'))
            ->with('teacher')
            ->each(function (TeacherSubscription $subscription) use (&$count) {
                $teacher = $subscription->teacher;

                if ($teacher && ! $teacher->trashed() && $teacher->status === Teacher::STATUS_ACTIVE) {
                    $teacher->update(['status' => Teacher::STATUS_SUSPENDED]);
                    $teacher->tokens()->delete();
                    $count++;
                }

                $subscription->update(['expired_handled_at' => now()]);
            });

        return $count;
    }
}
