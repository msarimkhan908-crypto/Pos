<?php

namespace App\Http\Requests;

use App\Models\Brand;
use App\Rules\ImageUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->brand() ? 'brands.edit' : 'brands.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'remove_logo' => $this->boolean('remove_logo'),
            'slug' => $this->filled('slug') ? str($this->input('slug'))->slug()->toString() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('brands')->ignore($this->brand())],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('brands')->ignore($this->brand())],
            'description' => ['nullable', 'string', 'max:1000'],
            'logo' => ImageUpload::rules(),
            'remove_logo' => ['boolean'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return ImageUpload::messages('logo');
    }

    /**
     * @return array<string, mixed>
     */
    public function brandData(): array
    {
        return $this->safe()->only(['name', 'slug', 'description', 'is_active']);
    }

    private function brand(): ?Brand
    {
        return $this->route('brand');
    }
}
