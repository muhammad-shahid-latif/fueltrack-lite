<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDailyClosingRequest extends FormRequest
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
        $money = ['required', 'numeric', 'min:0', 'max:1000000000'];

        return [
            'business_date' => ['required', 'date', 'before_or_equal:today'],
            'opening_cash' => $money,
            'card_sales' => $money,
            'actual_cash' => $money,
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['business_date' => 'date'];
    }
}
