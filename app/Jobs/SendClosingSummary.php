<?php

namespace App\Jobs;

use App\Models\DailyClosing;
use App\Services\WhatsApp\ClosingNotifier;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the day's WhatsApp summary to the owner. Dispatched after the
 * response of "Close day", so closing never waits on WhatsApp and no queue
 * worker is needed on the station PC.
 */
class SendClosingSummary
{
    use Dispatchable;

    public function __construct(public int $closingId) {}

    public function handle(ClosingNotifier $notifier): void
    {
        $closing = DailyClosing::find($this->closingId);

        if (! $closing || ! WhatsAppSettings::ready()) {
            return;
        }

        try {
            $notifier->send($closing);
        } catch (Throwable $exception) {
            // Never break the closing over a message; the failure is in the log.
            Log::warning('WhatsApp closing summary failed', ['closing' => $closing->id, 'error' => $exception->getMessage()]);
        }
    }
}
