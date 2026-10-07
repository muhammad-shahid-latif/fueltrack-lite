<?php

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/** A real 1×1 PNG, so tests do not need the GD extension. */
const TINY_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

function fakeImage(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, base64_decode(TINY_PNG));
}

beforeEach(function () {
    Storage::fake('local');
    $this->withoutVite();

    $this->owner = User::factory()->owner()->create();

    $this->form = fn (array $overrides = []) => array_merge([
        'name' => 'Waqas Khan',
        'designation' => 'Helper',
        'joined_on' => today()->toDateString(),
        'monthly_salary' => '22000',
        'cnic_number' => '3120211964533',
    ], $overrides);
});

test('a new employee can be added with a photo and CNIC copies', function () {
    $this->actingAs($this->owner)
        ->post(route('employees.store'), ($this->form)([
            'photo' => fakeImage('waqas.png'),
            'cnic_front' => fakeImage('front.png'),
            'cnic_back' => fakeImage('back.png'),
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $employee = Employee::sole();

    expect($employee->cnic_number)->toBe('31202-1196453-3')
        ->and($employee->photo_path)->toStartWith("employees/{$employee->id}/");

    foreach (array_keys(Employee::DOCUMENTS) as $document) {
        Storage::disk('local')->assertExists($employee->{Employee::DOCUMENTS[$document]});
    }

    $this->actingAs($this->owner)
        ->get(route('employees.show', $employee))
        ->assertOk()
        ->assertSee(['31202-1196453-3', route('employees.documents', [$employee, 'cnic_front'])], false);

    $this->actingAs($this->owner)
        ->get(route('employees.documents', [$employee, 'photo']))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('documents are private to the owner', function () {
    $this->actingAs($this->owner)->post(route('employees.store'), ($this->form)(['photo' => fakeImage('a.png')]));
    $employee = Employee::sole();

    $this->actingAs(User::factory()->staff()->create())
        ->get(route('employees.documents', [$employee, 'photo']))
        ->assertForbidden();

    $this->actingAs($this->owner)
        ->get(route('employees.documents', [$employee, 'cnic_back']))
        ->assertNotFound();

    expect(is_dir(public_path("employees/{$employee->id}")))->toBeFalse();
});

test('uploading a new photo replaces the old file, and documents can be removed', function () {
    $this->actingAs($this->owner)->post(route('employees.store'), ($this->form)(['photo' => fakeImage('old.png'), 'cnic_front' => fakeImage('f.png')]));
    $employee = Employee::sole();
    $oldPhoto = $employee->photo_path;

    $this->actingAs($this->owner)
        ->put(route('employees.update', $employee), ($this->form)(['photo' => fakeImage('new.png'), 'remove_cnic_front' => '1', 'is_active' => '1']))
        ->assertSessionHasNoErrors();

    $employee->refresh();

    Storage::disk('local')->assertMissing($oldPhoto);
    Storage::disk('local')->assertExists($employee->photo_path);
    expect($employee->cnic_front_path)->toBeNull();
});

test('uploads must be images and the CNIC must have 13 digits', function () {
    $this->actingAs($this->owner)
        ->post(route('employees.store'), ($this->form)([
            'cnic_number' => '12345',
            'photo' => UploadedFile::fake()->create('cv.pdf', 20, 'application/pdf'),
        ]))
        ->assertSessionHasErrors(['cnic_number', 'photo']);

    Employee::factory()->create(['cnic_number' => '31202-1196453-3']);

    $this->actingAs($this->owner)
        ->post(route('employees.store'), ($this->form)())
        ->assertSessionHasErrors('cnic_number');
});
