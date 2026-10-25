<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGadgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'brand' => ['required', 'string', 'max:100'],
            'category' => ['required', 'in:smartphone,laptop,audio,wearable,camera,accessory,other'],
            'store' => ['nullable', 'string', 'max:100'],
            'product_url' => ['nullable', 'url', 'max:2048'],
            'target_price' => ['required', 'integer', 'min:1'],
            'current_price' => ['required', 'integer', 'min:1'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'status' => ['sometimes', 'in:wishlist,purchased,dropped'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
