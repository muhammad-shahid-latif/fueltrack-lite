<?php

namespace App\Http\Controllers;

use App\Http\Requests\GiveOwedFuelRequest;
use App\Models\CustomerCredit;
use App\Models\OwedFuelDelivery;
use App\Services\CreditService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;

/**
 * Fuel billed on an earlier credit slip and pumped now.
 */
class OwedFuelController extends Controller
{
    public function store(GiveOwedFuelRequest $request, CustomerCredit $credit, CreditService $credits): RedirectResponse
    {
        $delivery = $credits->giveOwedFuel($credit, (float) $request->input('give_quantity'), $request->user(), $request->input('notes'));

        $credit->loadMissing('customer');
        $left = $credit->owedRemaining();

        return back()->with('success', sprintf(
            '%s given to %s against the slip of %s. %s',
            Format::liters($delivery->quantity),
            $credit->customer->name,
            $credit->business_date->format('d M'),
            $left > 0 ? Format::liters($left).' still owed.' : 'Slip fully given.',
        ));
    }

    public function destroy(OwedFuelDelivery $delivery, CreditService $credits): RedirectResponse
    {
        $credits->deleteOwedDelivery($delivery);

        return back()->with('success', 'Owed fuel delivery of '.Format::liters($delivery->quantity).' deleted. It is owed again.');
    }
}
