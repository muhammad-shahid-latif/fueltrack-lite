<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Erasing the station's data needs the owner's password and the word RESET,
 * so it cannot happen by a stray click.
 */
class ResetDataRequest extends FormRequest
{
    public const CONFIRM_WORD = 'RESET';

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
            'scope' => ['required', 'in:records,everything'],
            'confirm' => ['required', 'in:'.self::CONFIRM_WORD],
            'password' => ['required', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm.in' => 'Type RESET in capital letters to confirm.',
            'confirm.required' => 'Type RESET in capital letters to confirm.',
            'password.current_password' => 'The password is not correct.',
        ];
    }
}
