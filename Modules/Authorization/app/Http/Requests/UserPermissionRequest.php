<?php

namespace Modules\Authorization\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserPermissionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => __('messages.permission_name_format'),
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
