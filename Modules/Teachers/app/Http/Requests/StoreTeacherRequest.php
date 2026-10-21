<?php

namespace Modules\Teachers\app\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->username)) {
            $this->merge(['username' => strtolower(trim($this->username))]);
        }
    }

    public function messages(): array
    {
        // A machine-readable reason: the admin screen shows "username taken" from this code.
        return ['username.unique' => 'username_taken'];
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors();

        throw new HttpResponseException(response()->json([
            'message' => $errors->first(),
            'errors' => $errors->messages(),
            'code' => in_array('username_taken', $errors->get('username'), true) ? 'username_taken' : null,
        ], 422));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', 'unique:teachers,username'],
            // Optional: when omitted a temporary password is generated and returned once.
            'password' => ['nullable', 'string', Password::min(8)->letters()->mixedCase()->numbers()],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
