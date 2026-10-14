<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:owner'])
        ->get('/_test/owner-only', fn () => 'ok');
});

test('owners can access owner-only routes', function () {
    $this->actingAs(User::factory()->owner()->create())
        ->get('/_test/owner-only')
        ->assertOk();
});

test('staff are forbidden from owner-only routes', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/_test/owner-only')
        ->assertForbidden();
});

test('role helpers resolve enum and string roles', function () {
    $owner = User::factory()->owner()->make();

    expect($owner->isOwner())->toBeTrue()
        ->and($owner->hasRole('owner'))->toBeTrue()
        ->and($owner->hasRole(UserRole::Staff))->toBeFalse()
        ->and($owner->hasRole('unknown'))->toBeFalse();
});

test('settings link is only shown to owners', function () {
    // Labels of sidebar entries and of the pages inside each section.
    $labels = fn (User $user) => collect(Navigation::for($user))
        ->flatMap(fn ($group) => $group['items'])
        ->flatMap(fn ($item) => [$item['label'], ...array_column($item['tabs'] ?? [], 'label')]);

    expect($labels(User::factory()->owner()->make()))->toContain('Settings')
        ->and($labels(User::factory()->staff()->make()))->not->toContain('Settings');
});

test('demo seeder creates owner and staff accounts', function () {
    $this->seed();

    expect(User::firstWhere('email', 'owner@alnoor.com')->role)->toBe(UserRole::Owner)
        ->and(User::firstWhere('email', 'staff@alnoor.com')->role)->toBe(UserRole::Staff);
});
