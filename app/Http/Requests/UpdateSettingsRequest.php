<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
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
            'station_name' => ['required', 'string', 'max:100'],
            'station_address' => ['required', 'string', 'max:255'],
            'station_phone' => ['nullable', 'string', 'max:30'],
            'default_opening_cash' => ['required', 'numeric', 'min:0', 'max:100000000'],
        ];
    }
}
