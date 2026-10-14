<?php

use App\Models\User;
use App\Support\Navigation;
use App\Support\StaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    $this->owner = User::firstWhere('email', 'owner@alnoor.com');
    $this->staff = User::firstWhere('email', 'staff@alnoor.com');
});

function sidebarLabels(User $user): array
{
    return collect(Navigation::for($user))->flatMap(fn ($group) => array_column($group['items'], 'label'))->all();
}

test('every module covers routes that exist', function () {
    foreach (StaffAccess::MODULES as $key => $module) {
        foreach ($module['routes'] as $pattern) {
            expect(StaffAccess::routeExists($pattern))->toBeTrue("{$key}: {$pattern}");
        }
    }
});

test('by default the manager can use every module', function () {
    expect(StaffAccess::blocked())->toBe([])
        ->and(sidebarLabels($this->staff))->toContain('Khata register', 'Meter readings', 'Tanks');
});

test('the owner switches the khata register off for the manager', function () {
    $allowed = array_values(array_diff(array_keys(StaffAccess::MODULES), ['khata']));

    $this->actingAs($this->owner)
        ->put(route('access.update'), ['allowed' => $allowed])
        ->assertRedirect(route('access.edit'))
        ->assertSessionHas('success', 'Manager access saved. Turned off: Khata register.');

    expect(sidebarLabels($this->staff))->not->toContain('Khata register')
        ->and(sidebarLabels($this->owner))->toContain('Khata register');

    // A direct link is blocked too, with a clear message.
    $this->actingAs($this->staff)->get(route('credits.index'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('warning', 'The owner has turned off Khata register for your account.');

    $this->actingAs($this->staff)->get(route('customers.show', 1))->assertRedirect(route('dashboard'));
    $this->actingAs($this->staff)->getJson(route('credits.index'))->assertForbidden();

    // Selling on credit from the counter is part of the khata as well.
    $this->actingAs($this->staff)->get(route('sales.create'))->assertOk()->assertDontSee('Sell on credit');

    // The owner is never restricted.
    $this->actingAs($this->owner)->get(route('credits.index'))->assertOk();
});

test('a section loses only the tabs that are switched off', function () {
    StaffAccess::save(['meter_readings']);

    expect(sidebarLabels($this->staff))->not->toContain('Meter readings');

    $this->actingAs($this->staff)->get(route('nozzles.index'))->assertRedirect(route('dashboard'));
});

test('money on the dashboard can be hidden from the manager', function () {
    StaffAccess::save(['dashboard_money']);

    $this->actingAs($this->staff)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(["Today's sales", 'Cash in drawer', 'Rs. '])
        ->assertSee('Fuel sold');

    $this->actingAs($this->owner)->get(route('dashboard'))->assertSee("Today's sales");
});

test('only the owner manages access, from the Setup section', function () {
    $this->actingAs($this->staff)->get(route('access.edit'))->assertForbidden();
    $this->actingAs($this->staff)->put(route('access.update'), ['allowed' => []])->assertForbidden();

    $this->actingAs($this->owner)->get(route('access.edit'))
        ->assertOk()
        ->assertSeeText(['Station Manager', 'Money on the dashboard'])
        ->assertSee("Manager's menu", false)
        ->assertSeeInOrder(['section-tab', 'Shifts', 'Manager access', 'Settings'], false);
});

test('unknown modules are rejected', function () {
    $this->actingAs($this->owner)
        ->put(route('access.update'), ['allowed' => ['payroll']])
        ->assertSessionHasErrors('allowed.0');
});
