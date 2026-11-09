<?php

namespace App\Http\Requests\Need;

use Illuminate\Foundation\Http\FormRequest;

class StoreNeedRequest extends FormRequest
{
    /**
     * The category list is validated in the controller against the compiled
     * `needs` config: `Rule::in()` would duplicate the categories here and the
     * edge evaluates these rules at build time.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:120'],
            'category' => ['required', 'string'],
            'location' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Ceritakan kebutuhan yang ingin diajukan.',
            'title.min' => 'Judul kebutuhan minimal 3 karakter.',
            'title.max' => 'Judul kebutuhan maksimal 120 karakter.',
            'category.required' => 'Pilih kategori kebutuhan.',
            'location.max' => 'Patokan lokasi maksimal 120 karakter.',
        ];
    }
}
