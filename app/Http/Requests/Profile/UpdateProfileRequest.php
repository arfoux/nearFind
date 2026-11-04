<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * The rules live here (not inline) because a `Rule::` object inside
     * `$request->validate([...])` cannot be compiled for the edge: the dumped
     * FormRequest rules are the string form Laravel itself hands the validator.
     *
     * The rules are STATIC on purpose: the edge evaluates `rules()` at build
     * time, so `$this->user()` would bake a NULL id into
     * `unique()->ignore(...)`. The username uniqueness check runs in the
     * controller, against the row the request resolved.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => [
                'nullable',
                'string',
                'max:30',
                'regex:/^[a-zA-Z0-9._-]+$/',
            ],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+()\-\s]+$/'],
            'bio' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.max' => 'Nama lengkap maksimal 100 karakter.',
            'username.max' => 'Username maksimal 30 karakter.',
            'username.regex' => 'Username hanya boleh berisi huruf, angka, titik, garis bawah, dan tanda hubung.',
            'phone.max' => 'Nomor telepon terlalu panjang.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'bio.max' => 'Bio maksimal 300 karakter.',
        ];
    }
}
