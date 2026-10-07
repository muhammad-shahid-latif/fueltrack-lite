<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\SaveCustomerRequest;
use App\Models\Customer;
use App\Models\FuelProduct;
use App\Services\CreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(CreditService $credits): View
    {
        $summaries = $credits->summaries();
        $aging = array_fill_keys(array_keys(CreditService::AGING), 0.0);

        foreach ($summaries as $summary) {
            foreach ($summary['aging'] as $bucket => $amount) {
                $aging[$bucket] = round($aging[$bucket] + $amount, 2);
            }
        }

        return view('customers.index', [
            'summaries' => $summaries,
            'aging' => $aging,
            'totals' => [
                'receivable' => round($summaries->sum('balance'), 2),
                'overdue' => round($summaries->sum('overdue'), 2),
                'over_limit' => $summaries->filter(fn (array $row) => $row['available'] !== null && $row['available'] < 0)->count(),
                'accounts' => $summaries->where('balance', '>', 0)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer(['is_active' => true])]);
    }

    public function store(SaveCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($this->attributes($request) + ['is_active' => true]);

        return redirect()->route('customers.show', $customer)->with('success', "{$customer->name} added to the debt register.");
    }

    public function show(Request $request, Customer $customer, CreditService $credits): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return view('customers.show', [
            'account' => $credits->account($customer, $request->date('from'), $request->date('to')),
            'fuels' => FuelProduct::query()->fuel()->active()->orderBy('id')->get(),
            'paymentMethods' => PaymentMethod::cases(),
            'from' => $request->date('from'),
            'to' => $request->date('to'),
            'statement' => $request->boolean('statement'),
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', ['customer' => $customer]);
    }

    public function update(SaveCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->attributes($request) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('customers.show', $customer)->with('success', "{$customer->name} saved.");
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        if ($customer->credits()->exists() || $customer->payments()->exists()) {
            return back()->with('danger', "{$customer->name} has credit or payments on record, so the account cannot be deleted. Mark it inactive instead.");
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', "{$customer->name} removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SaveCustomerRequest $request): array
    {
        return [
            'name' => $request->string('name')->trim()->toString(),
            'phone' => $request->input('phone'),
            'vehicle_number' => $request->filled('vehicle_number') ? mb_strtoupper($request->string('vehicle_number')->trim()->toString()) : null,
            'address' => $request->input('address'),
            'credit_limit' => $request->filled('credit_limit') ? (float) $request->input('credit_limit') : null,
            'notes' => $request->input('notes'),
            ...($request->has('opening_balance') ? ['opening_balance' => (float) $request->input('opening_balance')] : []),
        ];
    }
}
