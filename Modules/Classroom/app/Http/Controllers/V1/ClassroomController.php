<?php

namespace Modules\Classroom\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Base for the teacher screens. A failure carries a machine-readable `code` next to the
 * usual envelope: the front-end branches on the code, never on the message.
 */
abstract class ClassroomController extends Controller
{
    protected function fail(string $code, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $code, 'data' => null, 'code' => $code], $status);
    }
}
