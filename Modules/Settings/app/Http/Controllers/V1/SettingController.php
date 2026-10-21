<?php

namespace Modules\Settings\app\Http\Controllers\V1;

use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Settings\app\Transformers\SettingResource;
use Modules\Settings\Services\SettingService;

class SettingController extends Controller {
    public function __construct(protected SettingService $service) {}

    public function index()
    {
        $settings = $this->service->all();
        return $this->successResponse(
            __('messages.settings_fetched'),
            SettingResource::collection($settings)
        );
    }

    public function update(Request $request, $key)
    {
        $request->validate([
            'value' => 'required',
            'type' => 'nullable|in:string,integer,boolean,json',
            'is_public' => 'nullable|boolean'
        ]);

        try {
            $this->service->update(
                $key,
                $request->value,
                $request->type ?? 'string',
                $request->is_public ?? true 
            );
            $updatedValue = $this->service->get($key);

            return $this->successResponse(
                __('messages.setting_updated'),
                [$key => $updatedValue]
            );
        } catch (Exception $e) {
            Log::error('Failed to update setting: ' . $e->getMessage());
            return $this->errorResponse(
                __('messages.setting_update_failed'),
                __('messages.unexpected_error')
            );
        }
    }


    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'required',
            'settings.*.type' => 'nullable|in:string,integer,boolean,json',
            'settings.*.is_public' => 'nullable|boolean',
        ]);

        try {
            // All or nothing: a failure part-way must not leave half the settings updated.
            DB::transaction(function () use ($request) {
                foreach ($request->settings as $item) {
                    $this->service->update(
                        $item['key'],
                        $item['value'],
                        $item['type'] ?? 'string',
                        $item['is_public'] ?? true
                    );
                }
            });

            $settings = $this->service->all();

            return $this->successResponse(
                __('messages.setting_updated'),
                SettingResource::collection($settings)
            );

        } catch (Exception $e) {
            Log::error('Bulk update failed: ' . $e->getMessage());

            return $this->errorResponse(
                __('messages.setting_update_failed'),
                __('messages.unexpected_error')
            );
        }
    }
}