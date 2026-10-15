<?php

use App\Models\DailyClosing;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsAppLog;
use App\Services\DailyClosingService;
use App\Support\WhatsAppSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
    Http::preventStrayRequests();
});

function connectWhatsApp(string $mode = 'template'): void
{
    WhatsAppSettings::save([
        'enabled' => true, 'recipients' => ['923001234567'], 'phone_number_id' => '106540352242922',
        'token' => str_repeat('EAAG', 10), 'mode' => $mode, 'template' => 'daily_closing_summary', 'language' => 'en',
    ]);
}

test('numbers are cleaned into international format', function () {
    expect(WhatsAppSettings::normalizeNumber('0300-1234567'))->toBe('923001234567')
        ->and(WhatsAppSettings::normalizeNumber('+92 300 1234567'))->toBe('923001234567')
        ->and(WhatsAppSettings::normalizeNumber('0092 300 1234567'))->toBe('923001234567')
        ->and(WhatsAppSettings::normalizeNumber('12345'))->toBeNull();
});

test('the owner saves the WhatsApp settings and the token is stored encrypted', function () {
    $this->actingAs($this->owner)
        ->put(route('whatsapp.update'), [
            'enabled' => '1', 'recipients' => "0300-1234567\n+92 321 7654321", 'phone_number_id' => '106540352242922',
            'token' => 'EAAGsecret-token-1234567890abcd', 'mode' => 'template', 'template' => 'daily_closing_summary', 'language' => 'en',
        ])
        ->assertRedirect(route('whatsapp.edit'))
        ->assertSessionHas('success');

    expect(WhatsAppSettings::recipients())->toBe(['923001234567', '923217654321'])
        ->and(WhatsAppSettings::token())->toBe('EAAGsecret-token-1234567890abcd')
        ->and(Setting::get('whatsapp_token'))->not->toContain('EAAGsecret')
        ->and(WhatsAppSettings::ready())->toBeTrue();

    // An empty token field keeps the saved token.
    $this->actingAs($this->owner)->put(route('whatsapp.update'), ['enabled' => '1', 'recipients' => '03001234567', 'phone_number_id' => '106540352242922', 'mode' => 'text'])
        ->assertSessionHasNoErrors();
    expect(WhatsAppSettings::token())->toBe('EAAGsecret-token-1234567890abcd');

    $this->actingAs($this->owner)->get(route('whatsapp.edit'))->assertOk()->assertDontSee('EAAGsecret-token')->assertSee('…abcd');
});

test('switching on without a number or connection is refused', function () {
    $this->actingAs($this->owner)
        ->put(route('whatsapp.update'), ['enabled' => '1', 'recipients' => 'abc', 'mode' => 'template'])
        ->assertSessionHasErrors(['recipients', 'phone_number_id', 'token']);
});

test('closing the day sends the template summary to the owner', function () {
    connectWhatsApp();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.TEST1']]])]);

    $this->actingAs($this->owner)
        ->post(route('closings.store'), ['business_date' => today()->toDateString(), 'opening_cash' => '0', 'actual_cash' => '0', 'card_sales' => '0'])
        ->assertRedirect();

    Http::assertSent(function (Request $request) {
        $body = $request->data();
        $values = array_column($body['template']['components'][0]['parameters'], 'text');

        return $request->url() === 'https://graph.facebook.com/v21.0/106540352242922/messages'
            && $request->hasHeader('Authorization', 'Bearer '.str_repeat('EAAG', 10))
            && $body['to'] === '923001234567'
            && $body['template']['name'] === 'daily_closing_summary'
            && count($values) === 6
            && str_contains($values[2], ' L (Petrol')
            && str_contains($values[5], 'Petrol')
            && collect($values)->every(fn ($v) => ! str_contains($v, "\n"));
    });

    expect(WhatsAppLog::sole())->status->toBe('sent')->message_id->toBe('wamid.TEST1');
});

test('free text mode sends the readable summary', function () {
    connectWhatsApp('text');
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.T2']]])]);
    $closing = app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);

    $this->actingAs($this->owner)->post(route('closings.whatsapp', $closing))->assertSessionHas('success');

    Http::assertSent(fn (Request $request) => $request['type'] === 'text'
        && str_contains($request['text']['body'], 'Fuel sold')
        && str_contains($request['text']['body'], 'Stock left'));
});

test('a rejected message is logged with a clear reason and never breaks closing', function () {
    connectWhatsApp();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'Invalid OAuth access token']], 401)]);

    $this->actingAs($this->owner)
        ->post(route('closings.store'), ['business_date' => today()->toDateString(), 'opening_cash' => '0', 'actual_cash' => '0', 'card_sales' => '0'])
        ->assertRedirect(route('closings.show', DailyClosing::latest('id')->first()));

    expect(WhatsAppLog::sole())->status->toBe('failed')
        ->and(WhatsAppLog::sole()->error)->toContain('access token is wrong or has expired');
});

test('nothing is sent while WhatsApp is switched off', function () {
    Http::fake();

    $this->actingAs($this->owner)
        ->post(route('closings.store'), ['business_date' => today()->toDateString(), 'opening_cash' => '0', 'actual_cash' => '0', 'card_sales' => '0']);

    Http::assertNothingSent();
});

test('the test button sends the latest closing', function () {
    connectWhatsApp();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.T3']]])]);
    app(DailyClosingService::class)->close(today(), openingCash: 0, actualCash: 0);

    $this->actingAs($this->owner)->post(route('whatsapp.test'))
        ->assertSessionHas('success', 'Test sent to +923001234567. Check WhatsApp.');

    expect(WhatsAppLog::sole()->purpose)->toBe('test');
});

test('only the owner manages WhatsApp, under Setup', function () {
    $this->actingAs($this->staff)->get(route('whatsapp.edit'))->assertForbidden();

    $this->actingAs($this->owner)->get(route('whatsapp.edit'))
        ->assertOk()
        ->assertSee(['Send the daily summary on WhatsApp', 'How to connect', 'Day closed at {{1}} for {{2}}.'])
        ->assertSeeInOrder(['section-tab', 'Manager access', 'WhatsApp', 'Settings'], false);
});
