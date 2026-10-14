<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\StaffAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Setup → Manager access: modules the owner switches off for the manager account.
 */
class StaffAccessController extends Controller
{
    public function edit(): View
    {
        return view('settings.access', [
            'groups' => StaffAccess::grouped(),
            'blocked' => StaffAccess::blocked(),
            'managers' => User::query()->where('role', UserRole::Staff->value)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'allowed' => ['array'],
            'allowed.*' => ['string', Rule::in(array_keys(StaffAccess::MODULES))],
        ]);

        $blocked = array_values(array_diff(array_keys(StaffAccess::MODULES), $validated['allowed'] ?? []));
        StaffAccess::save($blocked);

        $message = $blocked === []
            ? 'Manager access saved: everything is open to the manager.'
            : 'Manager access saved. Turned off: '.collect($blocked)->map(fn (string $key) => StaffAccess::MODULES[$key]['label'])->join(', ').'.';

        return redirect()->route('access.edit')->with('success', $message);
    }
}
