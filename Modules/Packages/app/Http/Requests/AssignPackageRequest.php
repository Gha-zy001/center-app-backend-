<?php

namespace Modules\Packages\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'packageId' => ['required', 'integer', 'exists:packages,id'],
            // true: start when the running package ends, instead of now (a renewal that keeps the days left).
            'extend' => ['sometimes', 'boolean'],
        ];
    }
}
