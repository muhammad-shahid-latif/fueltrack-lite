<?php

namespace App\Http\Requests;

use App\Enums\Shift;
use App\Support\ShiftSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The business date is never taken from the form; it comes from the server clock.
 */
class StoreMeterReadingRequest extends FormRequest
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
            'nozzle_id' => ['required', 'integer', Rule::exists('nozzles', 'id')->where('is_active', true)],
            'shift' => ['required', Rule::enum(Shift::class)->only(ShiftSchedule::active())],
            'opening_meter' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'closing_meter' => ['required', 'numeric', 'gt:opening_meter', 'max:999999999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nozzle_id' => 'nozzle'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['closing_meter.gt' => 'Closing meter must be greater than the opening meter.'];
    }
}
