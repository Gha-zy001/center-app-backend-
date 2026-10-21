<?php

namespace Modules\Classroom\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim(preg_replace('/\s+/u', ' ', $this->name)) : $this->name,
            'phone' => $this->normalizePhone($this->phone),
            'parentPhone' => $this->normalizePhone($this->parentPhone),
        ]);
    }

    public function rules(): array
    {
        $phone = ['nullable', 'string', 'regex:/^\+?\d{8,15}$/'];

        return [
            'name' => ['required', 'string', 'max:80'],
            'phone' => $phone,
            'parentPhone' => $phone,
            // Messages go to the parent, or to the student when there is no parent number.
            'groupId' => ['required', 'integer'],
        ];
    }

    public function after(): array
    {
        return [function ($validator) {
            if (! $this->phone && ! $this->parentPhone) {
                $validator->errors()->add('phone', 'At least one phone number is required.');
            }
        }];
    }

    private function normalizePhone(mixed $value): mixed
    {
        return is_string($value) ? preg_replace('/[\s\-()]/', '', $value) : $value;
    }
}
