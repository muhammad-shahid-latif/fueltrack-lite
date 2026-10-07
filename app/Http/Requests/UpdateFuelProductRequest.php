<?php

namespace App\Http\Requests;

use App\Enums\ProductUnit;
use App\Models\FuelProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Details only. Prices change through ChangeFuelPriceRequest so every
 * change lands in the price history.
 */
class UpdateFuelProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isOwner();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var FuelProduct $product */
        $product = $this->route('fuelProduct');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('fuel_products', 'name')->ignore($product->id)],
            'unit' => $product->isMetered()
                ? ['required', Rule::in([ProductUnit::Liter->value])]
                : ['required', Rule::enum(ProductUnit::class)],
            'low_stock_threshold' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['low_stock_threshold' => 'reorder level'];
    }
}
