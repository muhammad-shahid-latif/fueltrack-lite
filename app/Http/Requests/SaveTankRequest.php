<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTankRequest extends FormRequest
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
        $tank = $this->route('tank');
        $creating = $tank === null;

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('tanks', 'name')->ignore($tank)],
            // The product of a tank with history stays fixed.
            'fuel_product_id' => $creating || ! $tank->hasHistory()
                ? ['required', 'integer', Rule::exists('fuel_products', 'id')->where('type', ProductType::Fuel->value)->where('is_active', true)]
                : ['prohibited'],
            'capacity' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'opening_stock' => $creating ? ['required', 'numeric', 'min:0', 'lte:capacity'] : ['prohibited'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['fuel_product_id' => 'fuel', 'opening_stock' => 'current stock'];
    }
}
