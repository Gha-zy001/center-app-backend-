<?php

namespace Modules\Authorization\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateRoleRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|unique:roles,name,NULL,id,guard_name,admin',
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
