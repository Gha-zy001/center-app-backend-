<?php

namespace Modules\Packages\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Packages\app\Http\Resources\SubscriptionResource;
use Modules\Packages\app\Services\SubscriptionService;

/** What the signed-in teacher sees about their own package: which one, and when it ends. */
class TeacherSubscriptionController extends Controller
{
    public function show(Request $request, SubscriptionService $subscriptions): JsonResponse
    {
        $current = $subscriptions->current($request->user());

        return $this->successResponse('OK', ['subscription' => $current ? new SubscriptionResource($current) : null]);
    }
}
