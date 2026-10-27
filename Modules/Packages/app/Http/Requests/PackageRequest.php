<?php

namespace Modules\Packages\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create and update share the four fields; on update every one is optional. */
class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $package = $this->route('package');

        return [
            'name' => [$required, 'string', 'max:80', Rule::unique('packages', 'name')->ignore($package)],
            'durationDays' => [$required, 'integer', 'min:1', 'max:3650'],
            'maxUsers' => [$required, 'integer', 'min:1', 'max:1000000'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, mixed> the columns to save */
    public function columns(): array
    {
        $data = $this->validated();

        return array_filter([
            'name' => isset($data['name']) ? trim($data['name']) : null,
            'duration_days' => $data['durationDays'] ?? null,
            'max_users' => $data['maxUsers'] ?? null,
            'is_active' => $data['isActive'] ?? null,
        ], fn ($value) => $value !== null);
    }
}
