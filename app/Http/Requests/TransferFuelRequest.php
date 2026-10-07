<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferFuelRequest extends FormRequest
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
            'to_tank_id' => ['required', 'integer', Rule::exists('tanks', 'id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['to_tank_id' => 'destination tank'];
    }
}
