<?php

namespace Modules\Authorization\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|unique:roles,name,' . $this->route('role')->id . ',id,guard_name,admin',
            'permissions' => 'nullable|array',
            'permissions.*' => 'exists:permissions,name,guard_name,admin',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }
}
