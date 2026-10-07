<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The date of an advance always comes from the server clock.
 */
class StoreSalaryAdvanceRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->only([PaymentMethod::Cash, PaymentMethod::Bank])],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
