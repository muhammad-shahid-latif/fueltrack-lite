<?php

namespace App\Http\Requests;

use App\Enums\Shift;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Owner correction of a reading. The nozzle and business date never change.
 */
class UpdateMeterReadingRequest extends FormRequest
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
        return [
            'shift' => ['required', Rule::enum(Shift::class)],
            'opening_meter' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'closing_meter' => ['required', 'numeric', 'gt:opening_meter', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['closing_meter.gt' => 'Closing meter must be greater than the opening meter.'];
    }
}
