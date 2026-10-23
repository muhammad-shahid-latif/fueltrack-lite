<?php

use App\Models\User;
use App\Support\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutVite());

test('the six fuel networks are configured', function () {
    expect(Brand::all()->keys()->all())->toBe(['pso', 'parco-gunvor', 'shell', 'go', 'attock', 'askar'])
        ->and(Brand::find('shell')->fullName)->toBe('Shell Pakistan')
        ->and(Brand::find('askar')->fullName)->toBe('Askar Oil Services')
        ->and(Brand::find('nope'))->toBeNull();
});

test('the app opens on the network picker until one is chosen', function () {
    $this->get('/')->assertRedirect(route('brands.index'));

    $this->get(route('brands.index'))
        ->assertOk()
        ->assertSee(['Choose your fuel network', 'Pakistan State Oil', 'PARCO Gunvor Limited', 'Shell Pakistan', 'Gas &amp; Oil Pakistan', 'Attock Petroleum Limited', 'Askar Oil Services'], false);
});

test('choosing a network remembers it and themes the login page', function () {
    $this->post(route('brands.store'), ['brand' => 'pso'])
        ->assertRedirect(route('login'))
        ->assertCookie(Brand::COOKIE, 'pso');

    $this->withCookie(Brand::COOKIE, 'pso')->get('/')->assertRedirect(route('login'));

    $this->withCookie(Brand::COOKIE, 'pso')
        ->get(route('login'))
        ->assertOk()
        ->assertSee(['Pakistan State Oil dealer', '--ft-amber: #ffd100'], false);
});

test('an unknown network is refused', function () {
    $this->post(route('brands.store'), ['brand' => 'unknown'])->assertSessionHasErrors('brand');
});

test('every page wears the chosen network', function () {
    $this->withCookie(Brand::COOKIE, 'attock')
        ->actingAs(User::factory()->owner()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee(['Attock Petroleum Limited', '--ft-amber: #a6ce39', 'Change fuel network', 'images/brands/attock.png'], false);
});

test('a locked network skips the picker', function () {
    config(['fueltrack.brand' => 'go']);

    $this->get('/')->assertRedirect(route('login'));
    $this->get(route('brands.index'))->assertRedirect(route('home'));
    $this->post(route('brands.store'), ['brand' => 'shell'])->assertNotFound();

    $this->get(route('login'))->assertSee('Gas &amp; Oil Pakistan dealer', false)->assertDontSee('Fuel network:');
});

test('without a network the neutral theme is used', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('not chosen')
        ->assertDontSee('--ft-amber: #', false);
});

test('each network has its logo file', function () {
    Brand::all()->each(fn (Brand $brand) => expect($brand->logoUrl())->toContain("images/brands/{$brand->key}.png"));
});

test('the askar theme uses its navy, blue and orange', function () {
    $this->post(route('brands.store'), ['brand' => 'askar'])->assertCookie(Brand::COOKIE, 'askar');

    $this->withCookie(Brand::COOKIE, 'askar')
        ->get(route('login'))
        ->assertOk()
        ->assertSee(['Askar Oil Services dealer', '--ft-amber: #eb6e12', 'images/brands/askar.png'], false);
});
