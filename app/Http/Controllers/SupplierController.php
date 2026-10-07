<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\SaveSupplierRequest;
use App\Http\Requests\StorePartyPaymentRequest;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\SupplierService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(SupplierService $suppliers): View
    {
        $summaries = $suppliers->summaries();

        return view('suppliers.index', [
            'summaries' => $summaries,
            'schedule' => $suppliers->schedule(),
            'totals' => [
                'payable' => round($summaries->sum('balance'), 2),
                'overdue' => round($summaries->sum('overdue'), 2),
                'due_soon' => round($summaries->sum('due_soon'), 2),
                'purchased' => round($summaries->sum('purchased'), 2),
            ],
        ]);
    }

    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier(['is_active' => true, 'payment_terms_days' => 7])]);
    }

    public function store(SaveSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($this->attributes($request) + ['is_active' => true]);

        return redirect()->route('suppliers.show', $supplier)->with('success', "{$supplier->name} added to the supplier register.");
    }

    public function show(Supplier $supplier, SupplierService $suppliers): View
    {
        return view('suppliers.show', [
            'account' => $suppliers->account($supplier),
            'paymentMethods' => PaymentMethod::cases(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', ['supplier' => $supplier]);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($this->attributes($request) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('suppliers.show', $supplier)->with('success', "{$supplier->name} saved.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        if ($supplier->purchases()->exists() || $supplier->payments()->exists()) {
            return back()->with('danger', "{$supplier->name} has purchases or payments on record, so it cannot be deleted. Mark it inactive instead.");
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', "{$supplier->name} removed.");
    }

    public function pay(StorePartyPaymentRequest $request, Supplier $supplier, SupplierService $suppliers): RedirectResponse
    {
        $payment = $suppliers->recordPayment(
            supplier: $supplier,
            amount: (float) $request->input('amount'),
            method: $request->enum('payment_method', PaymentMethod::class),
            recordedBy: $request->user(),
            reference: $request->input('reference'),
            notes: $request->input('notes'),
        );

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Payment of '.Format::money($payment->amount)." to {$supplier->name} saved.");
    }

    public function destroyPayment(SupplierPayment $payment, SupplierService $suppliers): RedirectResponse
    {
        $suppliers->deletePayment($payment);

        return redirect()->route('suppliers.show', $payment->supplier_id)->with('success', 'Payment of '.Format::money($payment->amount).' deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(SaveSupplierRequest $request): array
    {
        return [
            'name' => $request->string('name')->trim()->toString(),
            'contact_person' => $request->input('contact_person'),
            'phone' => $request->input('phone'),
            'address' => $request->input('address'),
            'payment_terms_days' => $request->integer('payment_terms_days'),
            'notes' => $request->input('notes'),
            ...($request->has('opening_balance') ? ['opening_balance' => (float) $request->input('opening_balance')] : []),
        ];
    }
}
