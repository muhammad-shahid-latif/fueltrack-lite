<?php

namespace App\Services\WhatsApp;

use App\Support\WhatsAppSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sends messages through Meta's WhatsApp Cloud API.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api/messages
 */
class WhatsAppClient
{
    /**
     * @param  list<string>  $templateValues  body values {{1}}… for template mode
     * @return array{ok: bool, message_id: ?string, error: ?string}
     */
    public function send(string $to, string $text, array $templateValues = []): array
    {
        if (! WhatsAppSettings::configured()) {
            return ['ok' => false, 'message_id' => null, 'error' => 'WhatsApp is not connected: add the Phone Number ID and access token in Setup → WhatsApp.'];
        }

        $payload = ['messaging_product' => 'whatsapp', 'to' => $to];

        if (WhatsAppSettings::mode() === WhatsAppSettings::MODE_TEXT) {
            $payload += ['type' => 'text', 'text' => ['preview_url' => false, 'body' => $text]];
        } else {
            $payload += ['type' => 'template', 'template' => [
                'name' => WhatsAppSettings::templateName(),
                'language' => ['code' => WhatsAppSettings::templateLanguage()],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn (string $value) => ['type' => 'text', 'text' => $value], $templateValues),
                ]],
            ]];
        }

        $url = sprintf('%s/%s/%s/messages', rtrim(config('fueltrack.whatsapp.graph_url'), '/'), config('fueltrack.whatsapp.graph_version'), WhatsAppSettings::phoneNumberId());

        try {
            $response = Http::withToken((string) WhatsAppSettings::token())
                ->acceptJson()
                ->timeout((int) config('fueltrack.whatsapp.timeout', 15))
                ->post($url, $payload);
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'message_id' => null, 'error' => 'No internet connection to WhatsApp: '.$exception->getMessage()];
        }

        if ($response->successful()) {
            return ['ok' => true, 'message_id' => $response->json('messages.0.id'), 'error' => null];
        }

        $error = $response->json('error.error_data.details') ?? $response->json('error.message') ?? 'HTTP '.$response->status();

        return ['ok' => false, 'message_id' => null, 'error' => mb_substr($this->explain((int) $response->json('error.code'), (string) $error), 0, 500)];
    }

    /** Plain-language hints for the errors owners usually hit while setting up. */
    private function explain(int $code, string $error): string
    {
        $hint = match ($code) {
            190 => 'The access token is wrong or has expired. Create a permanent (system user) token in Meta Business Settings.',
            131030 => 'This number is not in the allowed list of the test number. Add it under WhatsApp → API Setup → To.',
            132001 => 'The template name or language does not match an approved template.',
            131047 => 'Free text only works within 24 hours of the owner messaging the business number. Use the template mode.',
            default => null,
        };

        return $hint ? "{$hint} ({$error})" : $error;
    }
}
