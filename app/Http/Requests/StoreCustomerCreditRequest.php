<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Fuel given on credit (quantity × today's price) or a plain amount.
 */
class StoreCustomerCreditRequest extends FormRequest
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
            'customer_id' => [$this->route('customer') ? 'prohibited' : 'required', 'integer', Rule::exists('customers', 'id')->where('is_active', true)],
            'fuel_product_id' => ['nullable', 'integer', Rule::exists('fuel_products', 'id')->where('type', ProductType::Fuel->value)->where('is_active', true)],
            'quantity' => ['required_with:fuel_product_id', 'nullable', 'numeric', 'gt:0', 'max:1000000'],
            'amount' => ['required_without:fuel_product_id', 'nullable', 'numeric', 'gt:0', 'max:100000000'],
            'vehicle_number' => ['nullable', 'string', 'max:30'],
            'slip_number' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['fuel_product_id' => 'fuel', 'customer_id' => 'customer'];
    }
}
