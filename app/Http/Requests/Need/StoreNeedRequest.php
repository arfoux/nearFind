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
            // The pin is what puts a need on the map, so it is required: a row
            // without coordinates is invisible everywhere but a list.
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'location' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:600'],
            'area' => ['nullable', 'string', 'max:120'],
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
            'latitude.required' => 'Tandai dulu titik lokasi kebutuhan di peta.',
            'latitude.numeric' => 'Titik lokasi tidak valid.',
            'latitude.between' => 'Titik lokasi di luar jangkauan peta.',
            'longitude.required' => 'Tandai dulu titik lokasi kebutuhan di peta.',
            'longitude.numeric' => 'Titik lokasi tidak valid.',
            'longitude.between' => 'Titik lokasi di luar jangkauan peta.',
            'location.max' => 'Patokan lokasi maksimal 120 karakter.',
            'description.max' => 'Deskripsi kebutuhan maksimal 600 karakter.',
            'area.max' => 'Nama wilayah maksimal 120 karakter.',
        ];
    }
}
