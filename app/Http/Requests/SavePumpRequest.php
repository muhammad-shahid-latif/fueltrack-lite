<?php

namespace App\Http\Requests;

use App\Models\Pump;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePumpRequest extends FormRequest
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
        /** @var Pump|null $pump */
        $pump = $this->route('pump');

        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('pumps', 'name')->ignore($pump?->id)],
            'is_active' => ['boolean'],
        ];
    }
}
