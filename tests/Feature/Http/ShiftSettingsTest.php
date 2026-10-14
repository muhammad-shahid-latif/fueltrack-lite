<?php

use App\Enums\Shift;
use App\Models\User;
use App\Support\ShiftSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
});

test('the default plan is three 8-hour shifts', function () {
    expect(ShiftSchedule::active())->toBe([Shift::Morning, Shift::Evening, Shift::Night])
        ->and(Shift::Morning->hours())->toBe('6:00 AM – 2:00 PM')
        ->and(Shift::Night->hours())->toBe('10:00 PM – 6:00 AM')
        ->and(Shift::at(Carbon::parse('2026-10-14 23:30')))->toBe(Shift::Night);
});

test('the owner switches the station to two 12-hour shifts', function () {
    $this->actingAs($this->owner)
        ->put(route('shifts.update'), ['shifts' => [['name' => 'Day', 'start' => '07:00'], ['name' => 'Night', 'start' => '19:00']]])
        ->assertRedirect(route('shifts.edit'))
        ->assertSessionHas('success', 'Shifts saved: 2 shifts (Day 7:00 AM – 7:00 PM, Night 7:00 PM – 7:00 AM).');

    expect(ShiftSchedule::active())->toBe([Shift::Morning, Shift::Evening])
        ->and(Shift::Morning->label())->toBe('Day')
        ->and(Shift::Evening->label())->toBe('Night')
        ->and(Shift::at(Carbon::parse('2026-10-14 03:00')))->toBe(Shift::Evening)
        ->and(Shift::at(Carbon::parse('2026-10-14 07:00')))->toBe(Shift::Morning);
});

test('a single 24-hour shift is allowed', function () {
    ShiftSchedule::save([['name' => 'Full day', 'start' => '08:00']]);

    expect(ShiftSchedule::active())->toBe([Shift::Morning])
        ->and(ShiftSchedule::shifts()[0]['minutes'])->toBe(1440)
        ->and(Shift::at(Carbon::parse('2026-10-14 05:00')))->toBe(Shift::Morning);
});

test('broken plans are rejected', function (array $shifts, string $message) {
    $this->actingAs($this->owner)
        ->from(route('shifts.edit'))
        ->put(route('shifts.update'), ['shifts' => $shifts])
        ->assertRedirect(route('shifts.edit'))
        ->assertSessionHasErrors(['shifts' => $message]);
})->with([
    'out of order' => [[['name' => 'A', 'start' => '06:00'], ['name' => 'B', 'start' => '22:00'], ['name' => 'C', 'start' => '14:00']], 'Start times must follow each other through the day (e.g. 06:00, 14:00, 22:00).'],
    'same names' => [[['name' => 'Day', 'start' => '06:00'], ['name' => 'day', 'start' => '18:00']], 'Each shift needs a different name.'],
    'too short' => [[['name' => 'A', 'start' => '06:00'], ['name' => 'B', 'start' => '06:30']], 'Each shift must be at least 1 hour, and the start times must follow each other through the day.'],
]);

test('only shifts in the plan can be used for new readings', function () {
    ShiftSchedule::save([['name' => 'Day', 'start' => '06:00'], ['name' => 'Night', 'start' => '18:00']]);

    $this->actingAs($this->staff)->get(route('meter-readings.create'))
        ->assertOk()
        ->assertSee(['Day', '6:00 AM – 6:00 PM', 'Night'])
        ->assertDontSee('shift_night', false);

    $this->actingAs($this->staff)
        ->post(route('meter-readings.store'), ['shift' => 'night'])
        ->assertSessionHasErrors('shift');
});

test('only the owner opens the shift settings, found under Setup', function () {
    $this->actingAs($this->staff)->get(route('shifts.edit'))->assertForbidden();

    $this->actingAs($this->owner)->get(route('shifts.edit'))
        ->assertOk()
        ->assertSee(['How many shifts?', 'Running now'])
        ->assertSeeInOrder(['section-tab', 'Products &amp; prices', 'Pumps &amp; nozzles', 'Shifts', 'Settings'], false);
});
