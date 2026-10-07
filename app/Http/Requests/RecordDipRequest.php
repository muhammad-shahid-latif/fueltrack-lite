<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordDipRequest extends FormRequest
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
            'dip_reading' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['dip_reading' => 'measured stock'];
    }
}
