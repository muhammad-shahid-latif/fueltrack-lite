<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use App\Enums\ProductUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFuelProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isOwner();
    }

    protected function prepareForValidation(): void
    {
        // Fuel is always measured in liters.
        if ($this->input('type') === ProductType::Fuel->value) {
            $this->merge(['unit' => ProductUnit::Liter->value]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('fuel_products', 'name')],
            'type' => ['required', Rule::enum(ProductType::class)],
            'unit' => ['required', Rule::enum(ProductUnit::class)],
            'selling_price' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'opening_stock' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'low_stock_threshold' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
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
