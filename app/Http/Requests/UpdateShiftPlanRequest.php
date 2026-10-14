<?php

namespace App\Http\Requests;

use App\Support\ShiftSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The station's shifts: 1 to 3, each with a name and a start time.
 */
class UpdateShiftPlanRequest extends FormRequest
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
            'shifts' => ['required', 'array', 'min:1', 'max:'.ShiftSchedule::MAX_SHIFTS],
            'shifts.*.name' => ['required', 'string', 'max:30'],
            'shifts.*.start' => ['required', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isEmpty() && ($problem = ShiftSchedule::problem($this->plan()))) {
                    $validator->errors()->add('shifts', $problem);
                }
            },
        ];
    }

    /**
     * @return list<array{name: string, start: string}>
     */
    public function plan(): array
    {
        return array_values(array_map(fn (array $row) => [
            'name' => trim((string) ($row['name'] ?? '')),
            'start' => (string) ($row['start'] ?? ''),
        ], (array) $this->input('shifts', [])));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['shifts.*.name' => 'shift name', 'shifts.*.start' => 'start time'];
    }
}
