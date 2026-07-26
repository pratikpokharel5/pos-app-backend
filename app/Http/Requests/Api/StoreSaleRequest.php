<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:50'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.address' => ['nullable', 'string'],
            'customer.notes' => ['nullable', 'string'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'additional_details' => ['nullable', 'array'],
            'sold_at' => ['nullable', 'date'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.item_name' => ['required_without:items.*.product_id', 'nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
            'items.*.additional_details' => ['nullable', 'array'],
            'items.*.custom_values' => ['nullable', 'array'],
            'items.*.custom_values.*.custom_field_id' => ['required_with:items.*.custom_values', 'exists:custom_fields,id'],
            'items.*.custom_values.*.value' => ['nullable', 'string'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::in(['cash', 'online'])],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'payments.*.provider' => ['nullable', 'string', 'max:255'],
            'payments.*.transaction_reference' => ['nullable', 'string', 'max:255'],
            'payments.*.notes' => ['nullable', 'string'],

            'custom_values' => ['nullable', 'array'],
            'custom_values.*.custom_field_id' => ['required_with:custom_values', 'exists:custom_fields,id'],
            'custom_values.*.value' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('customer_id') && $this->filled('customer')) {
                    $validator->errors()->add(
                        'customer',
                        'Use either an existing customer or a new customer, not both.'
                    );
                }

                foreach ($this->input('items', []) as $index => $item) {
                    if (
                        empty($item['product_id']) &&
                        (! array_key_exists('unit_price', $item) || $item['unit_price'] === null)
                    ) {
                        $validator->errors()->add(
                            "items.{$index}.unit_price",
                            'Unit price is required for custom items.'
                        );
                    }
                }
            },
        ];
    }
}
