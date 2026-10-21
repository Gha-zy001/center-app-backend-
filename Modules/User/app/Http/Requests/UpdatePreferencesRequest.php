<?php

namespace Modules\User\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notifications' => ['sometimes', 'array'],
            'notifications.email' => ['sometimes', 'boolean'],
            'notifications.sms' => ['sometimes', 'boolean'],
            'notifications.push' => ['sometimes', 'boolean'],
            'ui' => ['sometimes', 'array'],
            'ui.theme' => ['sometimes', 'string', 'in:light,dark,system'],
            'ui.compact_mode' => ['sometimes', 'boolean'],
        ];
    }
}