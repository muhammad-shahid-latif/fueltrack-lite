<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StoreCustomerCreditRequest;
use App\Http\Requests\StorePartyPaymentRequest;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\CustomerPayment;
use App\Models\FuelProduct;
use App\Services\CreditService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The debt register: credit sales and payments received, across customers.
 */
class CreditController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'customer' => ['nullable', 'integer', 'exists:customers,id'],
            'kind' => ['nullable', 'in:credit,payment'],
        ]);

        $from = $request->date('from') ?? BusinessClock::today()->subDays(29);
        $to = $request->date('to') ?? BusinessClock::today();
        $customerId = $request->integer('customer') ?: null;
        $kind = $request->input('kind');

        $scope = fn ($query) => $query
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to)
            ->when($customerId, fn ($q) => $q->where('customer_id', $customerId));

        $credits = $kind === 'payment' ? collect() : CustomerCredit::query()->tap($scope)->with(['customer', 'fuelProduct', 'recorder'])->get();
        $payments = $kind === 'credit' ? collect() : CustomerPayment::query()->tap($scope)->with(['customer', 'recorder'])->get();

        $entries = $credits->map(fn (CustomerCredit $credit) => ['kind' => 'credit', 'model' => $credit, 'date' => $credit->business_date, 'at' => $credit->created_at])
            ->concat($payments->map(fn (CustomerPayment $payment) => ['kind' => 'payment', 'model' => $payment, 'date' => $payment->business_date, 'at' => $payment->created_at]))
            ->sortByDesc(fn (array $entry) => $entry['date']->toDateString().' '.$entry['at']->format('H:i:s.u'))
            ->values();

        return view('credits.index', [
            'entries' => $entries,
            'totals' => [
                'credit' => round((float) $credits->sum('amount'), 2),
                'received' => round((float) $payments->sum('amount'), 2),
                'liters' => round((float) $credits->whereNotNull('fuel_product_id')->where('sale_id', null)->sum('quantity'), 2),
            ],
            'customers' => Customer::query()->orderBy('name')->get(),
            'from' => $from,
            'to' => $to,
            'filters' => $request->only(['customer', 'kind']),
        ]);
    }

    public function create(Request $request, CreditService $credits): View
    {
        $customers = Customer::query()->active()->orderBy('name')->get();

        return view('credits.create', [
            'customers' => $customers->map(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'vehicle' => $customer->vehicle_number,
                'balance' => $credits->balance($customer),
                'limit' => $customer->credit_limit !== null ? (float) $customer->credit_limit : null,
            ])->values(),
            'fuels' => FuelProduct::query()->fuel()->active()->orderBy('id')->get(),
            'selectedCustomerId' => $request->integer('customer') ?: null,
            'businessDate' => BusinessClock::today(),
        ]);
    }

    public function store(StoreCustomerCreditRequest $request, CreditService $credits): RedirectResponse
    {
        return $this->record($request, Customer::findOrFail($request->integer('customer_id')), $credits);
    }

    public function storeForCustomer(StoreCustomerCreditRequest $request, Customer $customer, CreditService $credits): RedirectResponse
    {
        return $this->record($request, $customer, $credits);
    }

    private function record(StoreCustomerCreditRequest $request, Customer $customer, CreditService $credits): RedirectResponse
    {
        $product = $request->filled('fuel_product_id') ? FuelProduct::findOrFail($request->integer('fuel_product_id')) : null;

        $credit = $credits->recordCredit(
            customer: $customer,
            product: $product,
            quantity: $request->filled('quantity') ? (float) $request->input('quantity') : null,
            amount: $request->filled('amount') ? (float) $request->input('amount') : null,
            vehicleNumber: $request->filled('vehicle_number') ? mb_strtoupper($request->string('vehicle_number')->trim()->toString()) : null,
            slipNumber: $request->input('slip_number'),
            recordedBy: $request->user(),
            notes: $request->input('notes'),
        );

        return redirect()->route('customers.show', $customer)->with('success', sprintf(
            'Credit sale saved: %s for %s. Balance now %s.',
            Format::money($credit->amount),
            $customer->name,
            Format::money($credits->balance($customer)),
        ));
    }

    public function destroy(CustomerCredit $credit, CreditService $credits): RedirectResponse
    {
        $credits->deleteCredit($credit);

        return redirect()->route('customers.show', $credit->customer_id)->with('success', 'Credit entry of '.Format::money($credit->amount).' deleted.');
    }

    public function receive(StorePartyPaymentRequest $request, Customer $customer, CreditService $credits): RedirectResponse
    {
        $payment = $credits->recordPayment(
            customer: $customer,
            amount: (float) $request->input('amount'),
            method: $request->enum('payment_method', PaymentMethod::class),
            recordedBy: $request->user(),
            reference: $request->input('reference'),
            notes: $request->input('notes'),
        );

        return redirect()->route('customers.show', $customer)->with('success', sprintf(
            'Received %s from %s. Balance now %s.',
            Format::money($payment->amount),
            $customer->name,
            Format::money($credits->balance($customer)),
        ));
    }

    public function destroyPayment(CustomerPayment $payment, CreditService $credits): RedirectResponse
    {
        $credits->deletePayment($payment);

        return redirect()->route('customers.show', $payment->customer_id)->with('success', 'Payment of '.Format::money($payment->amount).' deleted.');
    }
}
