<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner correction of a closed day's cash count.
 */
class UpdateDailyClosingRequest extends FormRequest
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
        $money = ['required', 'numeric', 'min:0', 'max:1000000000'];

        return [
            'opening_cash' => $money,
            'card_sales' => $money,
            'actual_cash' => $money,
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
