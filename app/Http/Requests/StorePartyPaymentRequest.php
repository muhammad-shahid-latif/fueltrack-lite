<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payment to a supplier or from a credit customer. The date is always
 * the server's business date.
 */
class StorePartyPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Money paid out to suppliers is an owner decision; staff may receive customer payments.
        return $this->routeIs('suppliers.*')
            ? (bool) $this->user()?->isOwner()
            : $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
