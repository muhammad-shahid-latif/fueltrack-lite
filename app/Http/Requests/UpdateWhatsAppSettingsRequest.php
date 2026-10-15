<?php

namespace App\Http\Requests;

use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWhatsAppSettingsRequest extends FormRequest
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
            'enabled' => ['boolean'],
            'recipients' => ['nullable', 'string', 'max:500'],
            'phone_number_id' => ['nullable', 'regex:/^\d{6,30}$/'],
            'token' => ['nullable', 'string', 'min:20', 'max:2000'],
            'mode' => ['required', Rule::in([WhatsAppSettings::MODE_TEMPLATE, WhatsAppSettings::MODE_TEXT])],
            'template' => ['nullable', 'regex:/^[a-z0-9_]{1,512}$/'],
            'language' => ['nullable', 'regex:/^[a-z]{2}(_[A-Z]{2})?$/'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ($this->rawNumbers() as $raw) {
                if (WhatsAppSettings::normalizeNumber($raw) === null) {
                    $validator->errors()->add('recipients', "\"{$raw}\" is not a valid mobile number. Use e.g. 0300-1234567 or +92 300 1234567.");
                }
            }

            if ($this->boolean('enabled')) {
                if ($this->recipients() === []) {
                    $validator->errors()->add('recipients', 'Add at least one number to send the daily summary to.');
                }

                if (! $this->filled('phone_number_id')) {
                    $validator->errors()->add('phone_number_id', 'The Phone Number ID from Meta is needed to send messages.');
                }

                if (! $this->filled('token') && WhatsAppSettings::token() === null) {
                    $validator->errors()->add('token', 'The access token from Meta is needed to send messages.');
                }
            }
        }];
    }

    /**
     * Numbers in international format, without duplicates.
     *
     * @return list<string>
     */
    public function recipients(): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (string $raw) => WhatsAppSettings::normalizeNumber($raw),
            $this->rawNumbers(),
        ))));
    }

    /**
     * @return list<string>
     */
    private function rawNumbers(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,\n;]+/', (string) $this->input('recipients')) ?: [])));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['phone_number_id' => 'Phone Number ID', 'token' => 'access token'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_number_id.regex' => 'The Phone Number ID is the long number from Meta (digits only), not the phone number itself.',
            'template.regex' => 'Template names use small letters, numbers and underscores, e.g. daily_closing_summary.',
            'language.regex' => 'Use a language code like en or en_US.',
        ];
    }
}
