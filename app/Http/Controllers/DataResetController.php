<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetDataRequest;
use App\Services\DataResetService;
use Illuminate\Http\RedirectResponse;

class DataResetController extends Controller
{
    public function __invoke(ResetDataRequest $request, DataResetService $reset): RedirectResponse
    {
        $keepSetup = $request->input('scope') === 'records';

        $reset->reset($request->user(), $keepSetup);

        return redirect()
            ->route($keepSetup ? 'dashboard' : 'fuel-products.index')
            ->with('success', $keepSetup
                ? 'All records erased. Fuel products, tanks, pumps and nozzles are kept with their current stock and meters.'
                : 'All data erased. Start by adding your fuel products, then tanks and pumps.');
    }
}
