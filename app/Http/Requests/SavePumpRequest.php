<?php

namespace App\Http\Requests;

use App\Enums\ProductType;
use App\Models\Pump;
use App\Models\Tank;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePumpRequest extends FormRequest
{
    /** Most nozzles one machine can have when it is added. */
    public const MAX_NOZZLES = 8;

    public function authorize(): bool
    {
        return (bool) $this->user()?->isOwner();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Pump|null $pump */
        $pump = $this->route('pump');

        $rules = [
            'name' => ['required', 'string', 'max:50', Rule::unique('pumps', 'name')->ignore($pump?->id)],
            'is_active' => ['boolean'],
        ];

        // A new machine can be added with its nozzles in one go (1, 2 or 4 on most dispensers).
        if ($this->isMethod('post')) {
            $rules += [
                'nozzles' => ['nullable', 'array', 'max:'.self::MAX_NOZZLES],
                'nozzles.*.fuel_product_id' => ['required', 'integer', Rule::exists('fuel_products', 'id')->where('type', ProductType::Fuel->value)->where('is_active', true)],
                'nozzles.*.tank_id' => ['nullable', 'integer', 'exists:tanks,id'],
                'nozzles.*.opening_meter' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            ];
        }

        return $rules;
    }

    /**
     * The tank of each nozzle must hold the nozzle's fuel.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tankFuel = Tank::query()->pluck('fuel_product_id', 'id');

            foreach ((array) $this->input('nozzles', []) as $i => $nozzle) {
                $tank = $nozzle['tank_id'] ?? null;

                if ($tank && (int) $tankFuel->get((int) $tank) !== (int) ($nozzle['fuel_product_id'] ?? 0)) {
                    $validator->errors()->add("nozzles.{$i}.tank_id", 'Nozzle '.($i + 1).': the tank holds a different fuel.');
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nozzles.*.fuel_product_id' => 'fuel',
            'nozzles.*.tank_id' => 'tank',
            'nozzles.*.opening_meter' => 'meter reading',
        ];
    }
}
