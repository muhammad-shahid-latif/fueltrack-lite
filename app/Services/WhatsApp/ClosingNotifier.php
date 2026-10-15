<?php

namespace App\Services\WhatsApp;

use App\Models\DailyClosing;
use App\Models\WhatsAppLog;
use App\Support\WhatsAppSettings;
use Illuminate\Support\Collection;

/**
 * Sends a closed day's summary to every number in Setup → WhatsApp and logs each attempt.
 */
class ClosingNotifier
{
    public function __construct(private WhatsAppClient $client, private ClosingSummary $summary) {}

    /**
     * @param  list<string>|null  $to  numbers to use instead of the saved ones (the test button)
     * @return Collection<int, WhatsAppLog>
     */
    public function send(DailyClosing $closing, string $purpose = 'closing', ?array $to = null): Collection
    {
        $text = $this->summary->text($closing);
        $values = $this->summary->templateValues($closing);

        return collect($to ?? WhatsAppSettings::recipients())->map(function (string $number) use ($closing, $purpose, $text, $values) {
            $result = $this->client->send($number, $text, $values);

            return WhatsAppLog::create([
                'purpose' => $purpose,
                'business_date' => $closing->business_date,
                'recipient' => $number,
                'status' => $result['ok'] ? 'sent' : 'failed',
                'message_id' => $result['message_id'],
                'error' => $result['error'],
            ]);
        });
    }
}
