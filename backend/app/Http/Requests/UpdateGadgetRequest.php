<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class UpdateGadgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'brand' => ['sometimes', 'required', 'string', 'max:100'],
            'category' => ['sometimes', 'required', 'in:smartphone,laptop,audio,wearable,camera,accessory,other'],
            'store' => ['nullable', 'string', 'max:100'],
            'product_url' => ['nullable', 'url', 'max:2048'],
            'target_price' => ['sometimes', 'required', 'integer', 'min:1'],
            'current_price' => ['sometimes', 'required', 'integer', 'min:1'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'status' => ['sometimes', 'in:wishlist,purchased,dropped'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        Log::warning('Validation failed for updating gadget', [
            'user' => (string) ($this->user()?->id ?? 'guest'),
            'action' => 'update_gadget_validation',
            'reason' => $validator->errors()->toArray(),
        ]);

        parent::failedValidation($validator);
    }
}
