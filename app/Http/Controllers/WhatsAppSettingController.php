<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateWhatsAppSettingsRequest;
use App\Models\DailyClosing;
use App\Models\WhatsAppLog;
use App\Services\WhatsApp\ClosingNotifier;
use App\Services\WhatsApp\ClosingSummary;
use App\Support\WhatsAppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Setup → WhatsApp: the daily closing summary sent to the owner's phone.
 */
class WhatsAppSettingController extends Controller
{
    public function edit(ClosingSummary $summary): View
    {
        $latest = DailyClosing::query()->latest('business_date')->first();

        return view('settings.whatsapp', [
            'enabled' => WhatsAppSettings::enabled(),
            'recipients' => WhatsAppSettings::recipients(),
            'phoneNumberId' => WhatsAppSettings::phoneNumberId(),
            'tokenHint' => WhatsAppSettings::tokenHint(),
            'mode' => WhatsAppSettings::mode(),
            'template' => WhatsAppSettings::templateName(),
            'language' => WhatsAppSettings::templateLanguage(),
            'templateBody' => ClosingSummary::templateBody(),
            'preview' => $latest ? $summary->text($latest) : null,
            'latest' => $latest,
            'logs' => WhatsAppLog::query()->latest('id')->limit(8)->get(),
        ]);
    }

    public function update(UpdateWhatsAppSettingsRequest $request): RedirectResponse
    {
        WhatsAppSettings::save([
            'enabled' => $request->boolean('enabled'),
            'recipients' => $request->recipients(),
            'phone_number_id' => $request->input('phone_number_id'),
            'token' => $request->input('token'),
            'mode' => $request->input('mode'),
            'template' => $request->input('template') ?: WhatsAppSettings::DEFAULT_TEMPLATE,
            'language' => $request->input('language') ?: 'en',
        ]);

        return redirect()->route('whatsapp.edit')->with('success', $request->boolean('enabled')
            ? 'WhatsApp saved. The summary will be sent every time a day is closed.'
            : 'WhatsApp saved. Daily summaries are switched off.');
    }

    /**
     * Sends the latest closed day's summary now, so the owner can check the setup.
     */
    public function test(Request $request, ClosingNotifier $notifier): RedirectResponse
    {
        $closing = DailyClosing::query()->latest('business_date')->first();

        if (! $closing) {
            return back()->with('warning', 'Close at least one day first: the test sends that day\'s summary.');
        }

        if (WhatsAppSettings::recipients() === []) {
            return back()->with('warning', 'Save at least one number first.');
        }

        $logs = $notifier->send($closing, 'test');
        $failed = $logs->reject->sent();

        return back()->with($failed->isEmpty() ? 'success' : 'danger', $failed->isEmpty()
            ? 'Test sent to '.$logs->pluck('recipient')->map(fn ($n) => '+'.$n)->join(', ').'. Check WhatsApp.'
            : 'WhatsApp did not accept the message: '.$failed->first()->error);
    }

    /**
     * Sends a closed day's summary again (e.g. after fixing the settings).
     */
    public function resend(DailyClosing $closing, ClosingNotifier $notifier): RedirectResponse
    {
        if (! WhatsAppSettings::configured() || WhatsAppSettings::recipients() === []) {
            return back()->with('warning', 'Set up WhatsApp first in Setup → WhatsApp.');
        }

        $failed = $notifier->send($closing)->reject->sent();

        return back()->with($failed->isEmpty() ? 'success' : 'danger', $failed->isEmpty()
            ? 'Summary for '.$closing->business_date->format('d M Y').' sent on WhatsApp.'
            : 'WhatsApp did not accept the message: '.$failed->first()->error);
    }
}
