<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CustomFieldRequest extends FormRequest
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
        $customFieldId = $this->route('custom_field')?->id ?? $this->route('customField')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('custom_fields', 'name')->ignore($customFieldId)],
            'label' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', Rule::in(['sale', 'sale_item'])],
            'field_type' => ['required', Rule::in(['text', 'number', 'date', 'select'])],
            'options' => ['nullable', 'array'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('field_type') !== 'select') {
                    return;
                }

                $options = $this->input('options', []);

                if (! is_array($options) || $options === []) {
                    $validator->errors()->add(
                        'options',
                        'Options are required for select custom fields.'
                    );
                }
            },
        ];
    }
}
