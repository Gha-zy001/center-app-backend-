<?php

namespace Modules\Classroom\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GroupRequest extends FormRequest
{
    public const WEEKDAYS = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim(preg_replace('/\s+/u', ' ', $this->name))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['string', 'distinct', Rule::in(self::WEEKDAYS)],
            'time' => ['required', 'date_format:H:i'],
        ];
    }
}
