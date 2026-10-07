<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The business date is never taken from the form; it comes from the server clock.
 */
class StoreFuelPurchaseRequest extends FormRequest
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
            'fuel_product_id' => ['required', 'integer', Rule::exists('fuel_products', 'id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'rate' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'tank_id' => ['nullable', 'integer', Rule::exists('tanks', 'id')->where('fuel_product_id', $this->integer('fuel_product_id'))->where('is_active', true)],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'supplier_name' => ['nullable', 'string', 'max:100'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'paid_now' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'fuel_product_id' => 'product',
            'rate' => 'purchase rate',
            'tank_id' => 'tank',
            'supplier_id' => 'supplier',
            'paid_now' => 'amount paid now',
        ];
    }
}
