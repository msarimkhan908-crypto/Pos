<?php

namespace App\Http\Requests;

use App\Models\ModifierGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ModifierGroupRequest extends FormRequest
{
    public const MAX_MODIFIERS = 50;

    public function authorize(): bool
    {
        return $this->user()->can($this->group() ? 'products.edit' : 'products.create');
    }

    protected function prepareForValidation(): void
    {
        $modifiers = collect($this->input('modifiers', []))
            ->filter(fn ($row) => is_array($row))
            ->map(fn (array $row) => [...$row, 'is_active' => filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)])
            ->values()
            ->all();

        $this->merge([
            'is_active' => $this->boolean('is_active'),
            'modifiers' => $modifiers,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('modifier_groups')->ignore($this->group())],
            'min_select' => ['required', 'integer', 'min:0', 'max:'.self::MAX_MODIFIERS],
            'max_select' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_MODIFIERS, 'gte:min_select'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'modifiers' => ['required', 'array', 'min:1', 'max:'.self::MAX_MODIFIERS],
            'modifiers.*.id' => $this->group()
                ? ['nullable', 'integer', Rule::exists('modifiers', 'id')->where('modifier_group_id', $this->group()->getKey())]
                : ['prohibited'],
            'modifiers.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'modifiers.*.price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.ProductRequest::MAX_PRICE],
            'modifiers.*.is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $available = collect($this->validated('modifiers'))->where('is_active', true)->count();

                if ((int) $this->validated('min_select') > $available) {
                    $validator->errors()->add('min_select', __('Customers must choose at least :min, but only :count active options exist.', [
                        'min' => $this->validated('min_select'),
                        'count' => $available,
                    ]));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'min_select' => 'minimum selections',
            'max_select' => 'maximum selections',
            'modifiers' => 'options',
            'modifiers.*.name' => 'option name',
            'modifiers.*.price' => 'option price',
        ];
    }

    public function messages(): array
    {
        return [
            'modifiers.required' => __('Add at least one option.'),
            'modifiers.*.name.distinct' => __('Option names must be unique within the group.'),
            'max_select.gte' => __('The maximum must be greater than or equal to the minimum.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function groupData(): array
    {
        return [
            ...$this->safe()->only(['name', 'min_select', 'max_select', 'is_active']),
            'sort_order' => (int) $this->validated('sort_order'),
        ];
    }

    private function group(): ?ModifierGroup
    {
        return $this->route('modifier_group');
    }
}
