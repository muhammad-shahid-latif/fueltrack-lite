<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The day's relief total for one fuel, copied from the government system.
 */
class StoreReliefSaleRequest extends FormRequest
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
            'business_date' => ['required', 'date', 'before_or_equal:today'],
            'fuel_product_id' => ['required', 'integer', Rule::exists('fuel_products', 'id')->where('type', ProductType::Fuel->value)],
            'tokens' => ['required', 'integer', 'min:1', 'max:65000'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'notes' => ['nullable', 'string', 'max:500'],
            'return' => ['nullable', Rule::in(['closing'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tokens.required' => 'Enter how many tokens were redeemed today.',
            'tokens.min' => 'Enter how many tokens were redeemed today.',
            'amount.required' => 'Enter the relief amount from the government system.',
            'amount.gt' => 'Enter the relief amount from the government system.',
        ];
    }
}
