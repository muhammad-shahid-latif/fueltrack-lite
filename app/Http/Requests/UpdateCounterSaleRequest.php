<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner correction of a counter sale. The product and date never change.
 */
class UpdateCounterSaleRequest extends FormRequest
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
            'quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
