<?php

namespace Modules\Packages\app\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Packages\app\Http\Requests\PackageRequest;
use Modules\Packages\app\Http\Resources\PackageResource;
use Modules\Packages\app\Models\Package;

/** The packages the center sells: name, length in days, how many users, and whether it can be given out. */
class AdminPackageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $packages = Package::query()
            ->withCount('subscriptions')
            ->when($request->has('isActive'), fn ($query) => $query->where('is_active', $request->boolean('isActive')))
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('id')
            ->paginate($this->perPage($request));

        return $this->paginated($packages, PackageResource::class, __('messages.packages_fetched_successfully'));
    }

    public function show(Package $package): JsonResponse
    {
        return $this->successResponse(__('messages.package_fetched_successfully'), new PackageResource($package->loadCount('subscriptions')));
    }

    public function store(PackageRequest $request): JsonResponse
    {
        $package = Package::create($request->columns());

        return $this->successResponse(__('messages.package_created_successfully'), new PackageResource($package), 201);
    }

    public function update(PackageRequest $request, Package $package): JsonResponse
    {
        $package->update($request->columns());

        return $this->successResponse(__('messages.package_updated_successfully'), new PackageResource($package->loadCount('subscriptions')));
    }

    // A package that was ever given to a teacher stays for the history: switch it off instead.
    public function destroy(Package $package): JsonResponse
    {
        if ($package->subscriptions()->exists()) {
            return $this->errorResponse(__('messages.package_in_use'), null, 409);
        }

        $package->delete();

        return $this->successResponse(__('messages.package_deleted_successfully'));
    }
}
