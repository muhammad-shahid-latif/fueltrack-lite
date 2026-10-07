<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSalarySlipRequest extends FormRequest
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
        $money = ['required', 'numeric', 'min:0', 'max:10000000'];

        return [
            'absent_days' => ['required', 'numeric', 'min:0', 'max:31', 'multiple_of:0.5'],
            'bonus' => $money,
            'other_deduction' => $money,
            'advance_deduction' => $money,
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['bonus' => 'bonus or overtime', 'other_deduction' => 'other deduction', 'advance_deduction' => 'advance recovery'];
    }
}
