<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    /**
     * The map picker may send `lat`/`lng`; the D1 contract uses the long names.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('latitude') && $this->has('lat')) {
            $this->merge(['latitude' => $this->input('lat')]);
        }

        if (! $this->has('longitude') && $this->has('lng')) {
            $this->merge(['longitude' => $this->input('lng')]);
        }
    }

    /**
     * The category list is checked in the controller against `needs.categories`,
     * the same way a need submission is: config stays the single source.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama tempat wajib diisi.',
            'name.max' => 'Nama tempat maksimal 120 karakter.',
            'category.required' => 'Pilih kategori tempat.',
            'latitude.required' => 'Tandai dulu titik lokasi di peta.',
            'latitude.numeric' => 'Titik lokasi tidak valid.',
            'latitude.between' => 'Titik lokasi di luar jangkauan peta.',
            'longitude.required' => 'Tandai dulu titik lokasi di peta.',
            'longitude.numeric' => 'Titik lokasi tidak valid.',
            'longitude.between' => 'Titik lokasi di luar jangkauan peta.',
            'address.max' => 'Alamat maksimal 255 karakter.',
            'area.max' => 'Nama wilayah maksimal 120 karakter.',
        ];
    }
}
