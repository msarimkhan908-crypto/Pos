<?php

namespace App\Http\Requests;

use App\Enums\RecordStatus;
use App\Models\Supplier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
{
    /** Digits, spaces, +, -, ( and ). */
    public const PHONE_PATTERN = '/^[0-9+\-\s()]{5,30}$/';

    public function authorize(): bool
    {
        return $this->user()->can($this->supplier() ? 'suppliers.edit' : 'suppliers.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['name', 'company_name', 'phone', 'email', 'tax_number', 'registration_number'])
            ->mapWithKeys(fn (string $field) => [$field => $this->filled($field) ? trim((string) $this->input($field)) : null])
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'company_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:'.self::PHONE_PATTERN],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => __('Enter a valid phone number (5–30 digits; spaces, +, - and brackets allowed).'),
        ];
    }

    private function supplier(): ?Supplier
    {
        return $this->route('supplier');
    }
}
