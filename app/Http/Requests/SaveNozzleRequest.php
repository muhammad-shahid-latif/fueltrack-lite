<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveNozzleRequest extends FormRequest
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
        $creating = $this->isMethod('post');

        return [
            'fuel_product_id' => [
                'required',
                'integer',
                Rule::exists('fuel_products', 'id')->where('type', ProductType::Fuel->value)->where('is_active', true),
            ],
            // The opening meter is set once; a replaced machine gets a new nozzle.
            'opening_meter' => $creating ? ['required', 'numeric', 'min:0', 'max:999999999999.99'] : ['prohibited'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['fuel_product_id' => 'fuel'];
    }
}
