<?php

namespace App\Http\Requests;

use App\Enums\TableStatus;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RestaurantTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->table() ? 'tables.edit' : 'tables.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('restaurant_tables')->ignore($this->table())],
            'section' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'status' => ['required', Rule::enum(TableStatus::class)],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tableData(): array
    {
        return [
            ...$this->safe()->only(['name', 'section', 'capacity', 'status', 'is_active']),
            'sort_order' => (int) $this->validated('sort_order'),
        ];
    }

    private function table(): ?RestaurantTable
    {
        return $this->route('table');
    }
}
