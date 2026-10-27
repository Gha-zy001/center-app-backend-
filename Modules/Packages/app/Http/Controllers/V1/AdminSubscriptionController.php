<?php

namespace Modules\Packages\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Packages\app\Http\Requests\AssignPackageRequest;
use Modules\Packages\app\Http\Resources\SubscriptionResource;
use Modules\Packages\app\Models\Package;
use Modules\Packages\app\Models\TeacherSubscription;
use Modules\Packages\app\Services\SubscriptionService;
use Modules\Teachers\app\Models\Teacher;

/** Which package each teacher is on, and giving or renewing one. */
class AdminSubscriptionController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    // The current subscription of every teacher that has one. Filters: status=active|expired, packageId, expiresInDays.
    public function index(Request $request): JsonResponse
    {
        $items = TeacherSubscription::query()
            ->with(['package', 'teacher'])
            ->whereNotExists(fn ($newer) => $newer->selectRaw('1')->from('teacher_subscriptions as next')
                ->whereColumn('next.teacher_id', 'teacher_subscriptions.teacher_id')
                ->where(fn ($later) => $later->whereColumn('next.ends_at', '>', 'teacher_subscriptions.ends_at')
                    ->orWhere(fn ($tie) => $tie->whereColumn('next.ends_at', 'teacher_subscriptions.ends_at')
                        ->whereColumn('next.id', '>', 'teacher_subscriptions.id'))))
            ->when($request->query('status') === 'active', fn ($query) => $query->where('ends_at', '>', now()))
            ->when($request->query('status') === 'expired', fn ($query) => $query->where('ends_at', '<=', now()))
            ->when($request->filled('packageId'), fn ($query) => $query->where('package_id', (int) $request->query('packageId')))
            ->when($request->filled('expiresInDays'), fn ($query) => $query
                ->where('ends_at', '>', now())->where('ends_at', '<=', now()->addDays((int) $request->query('expiresInDays'))))
            ->orderBy('ends_at')
            ->paginate($this->perPage($request));

        return $this->paginated($items, SubscriptionResource::class, __('messages.subscriptions_fetched_successfully'));
    }

    public function show(Teacher $teacher): JsonResponse
    {
        return $this->successResponse(__('messages.subscription_fetched_successfully'), [
            'current' => ($current = $this->subscriptions->current($teacher)) ? new SubscriptionResource($current) : null,
            'history' => SubscriptionResource::collection(
                TeacherSubscription::with('package')->where('teacher_id', $teacher->id)->orderByDesc('ends_at')->orderByDesc('id')->limit(50)->get()
            ),
        ]);
    }

    public function store(AssignPackageRequest $request, Teacher $teacher): JsonResponse
    {
        $package = Package::findOrFail($request->validated('packageId'));

        if (! $package->is_active) {
            return $this->errorResponse(__('messages.package_inactive'), null, 422);
        }

        $subscription = $this->subscriptions->assign($teacher, $package, (bool) $request->validated('extend', false));

        return $this->successResponse(__('messages.subscription_assigned_successfully'), new SubscriptionResource($subscription), 201);
    }
}
