<?php

namespace App\Http\Requests;

use App\Support\StaffAccess;
use Illuminate\Foundation\Http\FormRequest;

class ChangeFuelPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The manager may change prices unless the owner switched it off (Setup → Manager access).
        return StaffAccess::can($this->user(), 'prices') && $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['price' => 'new price'];
    }
}
