<?php

namespace App\Http\Requests;

use App\Enums\RecordStatus;
use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->customer() ? 'customers.edit' : 'customers.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(collect(['name', 'phone', 'email'])
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
            'phone' => ['nullable', 'string', 'regex:'.SupplierRequest::PHONE_PATTERN],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
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

    private function customer(): ?Customer
    {
        return $this->route('customer');
    }
}
