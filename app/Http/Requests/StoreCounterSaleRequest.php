<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCounterSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fuel_product_id' => [
                'required',
                'integer',
                Rule::exists('fuel_products', 'id')
                    ->where('is_active', true)
                    ->whereNot('type', ProductType::Fuel->value),
            ],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where('is_active', true)],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['fuel_product_id' => 'product', 'customer_id' => 'customer'];
    }
}
