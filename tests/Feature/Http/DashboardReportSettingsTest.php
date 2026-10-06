<?php

use App\Models\FuelProduct;
use App\Models\Setting;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
});

test('the dashboard shows today\'s live figures', function () {
    $today = app(DailyClosingService::class)->summary(today());

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(Format::money($today['total_sales']))
        ->assertSee(Format::liters($today['total_liters']))
        ->assertSee(['fuel prices', 'Sales, last 7 days', 'Stock', 'Recent closings', 'Cash in drawer (est.)', 'Engine Oil 1L']);
});

test('the dashboard warns about low stock', function () {
    FuelProduct::firstWhere('code', 'diesel')->update(['low_stock_threshold' => 999999]);

    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertSee('Running low:');
});

test('the daily report defaults to today and can span a range', function () {
    $this->actingAs($this->staff)
        ->get(route('reports.daily'))
        ->assertOk()
        ->assertSee(['Daily Sales Report', today()->format('d F Y'), 'Print / Save PDF', 'Sales by product'])
        ->assertDontSee('Day by day');

    $this->actingAs($this->staff)
        ->get(route('reports.daily', ['from' => today()->subDays(6)->toDateString(), 'to' => today()->toDateString()]))
        ->assertOk()
        ->assertSee(['Sales Report', 'Day by day', today()->subDays(6)->format('D, d M')]);
});

test('report dates are validated', function () {
    $this->actingAs($this->staff)
        ->get(route('reports.daily', ['from' => today()->toDateString(), 'to' => today()->subDay()->toDateString()]))
        ->assertSessionHasErrors('to');
});

test('owners can update station settings', function () {
    $this->actingAs($this->owner)
        ->put(route('settings.update'), [
            'station_name' => 'Al-Noor Petroleum',
            'station_address' => 'Model Town, Bahawalpur',
            'station_phone' => '0300-1234567',
            'default_opening_cash' => '150000',
        ])
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHas('success');

    expect(Setting::get('station_name'))->toBe('Al-Noor Petroleum')
        ->and(Setting::get('default_opening_cash'))->toBe('150000');

    $this->actingAs($this->owner)->get(route('dashboard'))->assertSee('Al-Noor Petroleum');
    $this->actingAs($this->owner)->get(route('closings.create'))->assertSee('150000');
});

test('staff cannot open settings', function () {
    $this->actingAs($this->staff)->get(route('settings.edit'))->assertForbidden();
    $this->actingAs($this->staff)
        ->put(route('settings.update'), ['station_name' => 'X', 'station_address' => 'Y', 'default_opening_cash' => '0'])
        ->assertForbidden();
});

test('settings are validated', function () {
    $this->actingAs($this->owner)
        ->put(route('settings.update'), ['station_name' => '', 'station_address' => '', 'default_opening_cash' => '-1'])
        ->assertSessionHasErrors(['station_name', 'station_address', 'default_opening_cash']);
});

test('every sidebar module is live', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertDontSee('nav-link disabled', false);
});

test('the sidebar shows the server business date', function () {
    $this->actingAs($this->owner)
        ->get(route('dashboard'))
        ->assertSee(['Business date (server)', now()->format('d M Y')]);
});
