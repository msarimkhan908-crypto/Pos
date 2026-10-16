<?php

namespace App\Http\Requests;

use App\Models\Category;
use App\Rules\ImageUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->category() ? 'categories.edit' : 'categories.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'remove_image' => $this->boolean('remove_image'),
            'slug' => $this->filled('slug') ? str($this->input('slug'))->slug()->toString() : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($this->category())],
            'slug' => ['nullable', 'string', 'max:120', 'alpha_dash', Rule::unique('categories')->ignore($this->category())],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ImageUpload::rules(),
            'remove_image' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'kitchen_station_id' => ['nullable', 'integer', Rule::exists('kitchen_stations', 'id')],
        ];
    }

    public function messages(): array
    {
        return ImageUpload::messages('image');
    }

    /**
     * Attributes to save (image handled separately).
     *
     * @return array<string, mixed>
     */
    public function categoryData(): array
    {
        return [
            ...$this->safe()->only(['name', 'slug', 'description', 'is_active']),
            'kitchen_station_id' => $this->validated('kitchen_station_id'),
            'sort_order' => (int) $this->validated('sort_order'),
        ];
    }

    private function category(): ?Category
    {
        return $this->route('category');
    }
}
