<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSupplierRequest extends FormRequest
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
        $supplier = $this->route('supplier');

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('suppliers', 'name')->ignore($supplier)],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            // Fixed once the account has history so the ledger never shifts.
            'opening_balance' => $supplier && ($supplier->purchases()->exists() || $supplier->payments()->exists())
                ? ['prohibited']
                : ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['payment_terms_days' => 'credit days'];
    }
}
