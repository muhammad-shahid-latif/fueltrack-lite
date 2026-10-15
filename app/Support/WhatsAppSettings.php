<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * WhatsApp Cloud API settings from Setup → WhatsApp.
 * The access token is stored encrypted with the app key.
 */
final class WhatsAppSettings
{
    public const MODE_TEMPLATE = 'template';

    public const MODE_TEXT = 'text';

    public const DEFAULT_TEMPLATE = 'daily_closing_summary';

    public static function enabled(): bool
    {
        return Setting::get('whatsapp_enabled') === '1';
    }

    /**
     * Numbers that get the daily summary, in international format without "+".
     *
     * @return list<string>
     */
    public static function recipients(): array
    {
        return array_values(array_filter(explode(',', (string) Setting::get('whatsapp_recipients'))));
    }

    public static function phoneNumberId(): ?string
    {
        return Setting::get('whatsapp_phone_number_id') ?: null;
    }

    public static function token(): ?string
    {
        $stored = Setting::get('whatsapp_token');

        if (! $stored) {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null; // App key changed: the token must be entered again.
        }
    }

    /** Last 4 characters, to show that a token is saved without revealing it. */
    public static function tokenHint(): ?string
    {
        $token = self::token();

        return $token ? '…'.substr($token, -4) : null;
    }

    public static function mode(): string
    {
        return Setting::get('whatsapp_mode') === self::MODE_TEXT ? self::MODE_TEXT : self::MODE_TEMPLATE;
    }

    public static function templateName(): string
    {
        return Setting::get('whatsapp_template') ?: self::DEFAULT_TEMPLATE;
    }

    public static function templateLanguage(): string
    {
        return Setting::get('whatsapp_language') ?: 'en';
    }

    /** Ready to send: switched on, connected and with at least one number. */
    public static function ready(): bool
    {
        return self::enabled() && self::configured() && self::recipients() !== [];
    }

    public static function configured(): bool
    {
        return self::phoneNumberId() !== null && self::token() !== null;
    }

    /**
     * @param  array{enabled: bool, recipients: list<string>, phone_number_id: ?string, token: ?string, mode: string, template: ?string, language: ?string}  $values
     */
    public static function save(array $values): void
    {
        $settings = [
            'whatsapp_enabled' => $values['enabled'] ? '1' : '0',
            'whatsapp_recipients' => implode(',', $values['recipients']),
            'whatsapp_phone_number_id' => $values['phone_number_id'],
            'whatsapp_mode' => $values['mode'],
            'whatsapp_template' => $values['template'],
            'whatsapp_language' => $values['language'],
        ];

        // An empty token field keeps the saved one.
        if (filled($values['token'])) {
            $settings['whatsapp_token'] = Crypt::encryptString($values['token']);
        }

        Setting::putMany($settings);
    }

    /**
     * "0300-1234567", "+92 300 1234567" and "0092300…" all become "923001234567".
     * Numbers starting with a single 0 are taken as Pakistani.
     */
    public static function normalizeNumber(string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '92'.substr($digits, 1);
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }
}
