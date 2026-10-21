<?php

namespace Modules\User\app\Http\Controllers\V1;


use App\Http\Controllers\Controller; 
use App\Support\Query\BaseQueryService;
use Illuminate\Http\Request;
use Modules\User\app\Models\User;
use Modules\User\app\Transformers\UserResource;

class UserController extends Controller
{

    public function index(Request $request)
    {
        abort_unless($request->user()->can('view-users'), 403);

        $query = User::query()->with('roles');

        $users = (new BaseQueryService($query, $request))
            ->allowedFilters(['status', 'email', 'roles.name'])
            ->allowedSorts(['id', 'name', 'created_at'])
            ->allowedSearch(['name', 'email','phone'])
            ->apply()
            ->paginate($this->perPage($request));

        return $this->paginated($users, UserResource::class, 'تم جلب البيانات بنجاح');
    }
}