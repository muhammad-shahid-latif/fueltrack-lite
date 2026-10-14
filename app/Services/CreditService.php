<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\CustomerPayment;
use App\Models\FuelProduct;
use App\Models\OwedFuelDelivery;
use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\Settlement;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Khata register: what each customer owes is
 * opening balance + credit sales − payments received.
 *
 * Fuel on credit is already part of the nozzle sales, so a fuel credit entry
 * only marks that amount as unpaid. Counter items on credit are recorded as
 * a counter sale (SaleService) and linked here.
 *
 * Owed fuel: a slip can be billed in full while part of the fuel is given on
 * a later day (owed_quantity). The later pumping is recorded as an
 * OwedFuelDelivery so each day's closing knows which credit fuel actually
 * left the nozzles that day.
 */
class CreditService
{
    /** Aging buckets in days since the credit sale. */
    public const AGING = ['0–30 days' => [0, 30], '31–60 days' => [31, 60], '61–90 days' => [61, 90], '90+ days' => [91, PHP_INT_MAX]];

    /** Customer debts are treated as due this many days after the sale. */
    public const TERMS_DAYS = 30;

    public function __construct(private DailyClosingService $closings) {}

    /**
     * Fuel (or any amount) given to a customer on credit.
     *
     * @throws ValidationException
     */
    public function recordCredit(
        Customer $customer,
        ?FuelProduct $product,
        ?float $quantity,
        ?float $amount,
        ?string $vehicleNumber = null,
        ?string $slipNumber = null,
        ?User $recordedBy = null,
        ?string $notes = null,
        DateTimeInterface|string|null $date = null,
        float $owedQuantity = 0,
    ): CustomerCredit {
        $date = Carbon::parse($date ?? BusinessClock::today())->toDateString();

        return DB::transaction(function () use ($customer, $product, $quantity, $amount, $vehicleNumber, $slipNumber, $recordedBy, $notes, $date, $owedQuantity) {
            $this->closings->assertOpen($date);
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

            if ($product && ! $product->isMetered()) {
                throw ValidationException::withMessages(['fuel_product_id' => 'Counter items on credit are recorded from the counter sale page.']);
            }

            $rate = $product ? round((float) $product->selling_price, 2) : null;
            $total = $product ? round((float) $quantity * $rate, 2) : round((float) $amount, 2);

            if ($total <= 0) {
                throw ValidationException::withMessages([$product ? 'quantity' : 'amount' => 'Enter the quantity or amount given on credit.']);
            }

            $owedQuantity = round($owedQuantity, 2);

            if ($owedQuantity < 0 || ($owedQuantity > 0 && ! $product) || $owedQuantity > round((float) $quantity, 2)) {
                throw ValidationException::withMessages(['given_quantity' => 'Liters given now must be between 0 and the liters on the slip.']);
            }

            $this->assertWithinLimit($customer, $total);

            return $customer->credits()->create([
                'fuel_product_id' => $product?->id,
                'business_date' => $date,
                'quantity' => $product ? round((float) $quantity, 2) : null,
                'owed_quantity' => $owedQuantity,
                'rate' => $rate,
                'amount' => $total,
                'vehicle_number' => $vehicleNumber ?: $customer->vehicle_number,
                'slip_number' => $slipNumber,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Links a counter sale to a customer's account. Runs inside the sale's transaction.
     *
     * @throws ValidationException
     */
    public function attachSale(Sale $sale, Customer $customer, ?string $vehicleNumber = null, ?User $recordedBy = null): CustomerCredit
    {
        $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

        if (! $customer->is_active) {
            throw ValidationException::withMessages(['customer_id' => "{$customer->name}'s account is inactive."]);
        }

        $this->assertWithinLimit($customer, (float) $sale->amount, 'customer_id');

        return $customer->credits()->create([
            'sale_id' => $sale->id,
            'fuel_product_id' => $sale->fuel_product_id,
            'business_date' => $sale->business_date,
            'quantity' => $sale->quantity,
            'rate' => $sale->rate,
            'amount' => $sale->amount,
            'vehicle_number' => $vehicleNumber ?: $customer->vehicle_number,
            'recorded_by' => $recordedBy?->id,
            'notes' => $sale->notes,
        ]);
    }

    /**
     * Keeps a credit entry equal to its counter sale after a correction.
     *
     * @throws ValidationException
     */
    public function syncSale(Sale $sale): void
    {
        $credit = CustomerCredit::query()->where('sale_id', $sale->id)->first();

        if (! $credit) {
            return;
        }

        $increase = (float) $sale->amount - (float) $credit->amount;

        if ($increase > 0) {
            $this->assertWithinLimit(Customer::query()->findOrFail($credit->customer_id), $increase, 'quantity');
        }

        $credit->update([
            'quantity' => $sale->quantity,
            'rate' => $sale->rate,
            'amount' => $sale->amount,
            'notes' => $sale->notes,
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function deleteCredit(CustomerCredit $credit): void
    {
        if ($credit->isCounterSale()) {
            throw ValidationException::withMessages(['record' => 'This credit came from a counter sale. Delete the sale instead.']);
        }

        DB::transaction(function () use ($credit) {
            $this->closings->assertOpen($credit->business_date, 'record');

            if ($credit->owedDeliveries()->exists()) {
                throw ValidationException::withMessages(['record' => 'Part of the owed fuel on this slip has already been given. Delete those deliveries first.']);
            }

            $credit->delete();
        });
    }

    /**
     * Pumps owed fuel against an earlier slip. Nothing new is billed; the
     * closing treats it like credit fuel given today.
     *
     * @throws ValidationException
     */
    public function giveOwedFuel(
        CustomerCredit $credit,
        float $quantity,
        ?User $recordedBy = null,
        ?string $notes = null,
        DateTimeInterface|string|null $date = null,
    ): OwedFuelDelivery {
        $date = Carbon::parse($date ?? BusinessClock::today())->toDateString();

        return DB::transaction(function () use ($credit, $quantity, $recordedBy, $notes, $date) {
            $this->closings->assertOpen($date);
            $credit = CustomerCredit::query()->lockForUpdate()->findOrFail($credit->id);
            $remaining = $credit->owedRemaining();
            $quantity = round($quantity, 2);

            if ($remaining <= 0) {
                throw ValidationException::withMessages(['give_quantity' => 'All the fuel on this slip has already been given.']);
            }

            if ($quantity <= 0 || $quantity > $remaining) {
                throw ValidationException::withMessages(['give_quantity' => 'Enter up to '.number_format($remaining, 2).' L, the fuel still owed on this slip.']);
            }

            // The last delivery takes whatever value is left, so the parts add up to the slip exactly.
            $amount = abs($quantity - $remaining) < 0.005
                ? round($credit->owedValue() - (float) $credit->owedDeliveries()->sum('amount'), 2)
                : round($quantity * (float) $credit->rate, 2);

            return $credit->owedDeliveries()->create([
                'customer_id' => $credit->customer_id,
                'fuel_product_id' => $credit->fuel_product_id,
                'business_date' => $date,
                'quantity' => $quantity,
                'amount' => $amount,
                'vehicle_number' => $credit->vehicle_number,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function deleteOwedDelivery(OwedFuelDelivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $this->closings->assertOpen($delivery->business_date, 'record');
            $delivery->delete();
        });
    }

    /**
     * Slips with fuel still to give, oldest first.
     *
     * @return Collection<int, CustomerCredit>
     */
    public function openOwed(?Customer $customer = null): Collection
    {
        return CustomerCredit::query()
            ->with(['customer', 'fuelProduct', 'recorder'])
            ->withSum('owedDeliveries as owed_delivered_sum', 'quantity')
            ->where('owed_quantity', '>', 0)
            ->when($customer, fn ($query) => $query->where('customer_id', $customer->id))
            ->orderBy('business_date')
            ->orderBy('id')
            ->get()
            ->filter(fn (CustomerCredit $credit) => $credit->owedRemaining() > 0)
            ->values();
    }

    /**
     * @throws ValidationException
     */
    public function recordPayment(
        Customer $customer,
        float $amount,
        PaymentMethod $method = PaymentMethod::Cash,
        ?User $recordedBy = null,
        ?string $reference = null,
        ?string $notes = null,
        DateTimeInterface|string|null $date = null,
    ): CustomerPayment {
        $date = Carbon::parse($date ?? BusinessClock::today())->toDateString();

        return DB::transaction(function () use ($customer, $amount, $method, $recordedBy, $reference, $notes, $date) {
            $this->closings->assertOpen($date);
            $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $balance = $this->balance($customer);

            if ($amount > $balance + 0.005) {
                throw ValidationException::withMessages([
                    'amount' => $balance > 0
                        ? "{$customer->name} owes only Rs. ".number_format($balance, 2).'.'
                        : "{$customer->name} owes nothing.",
                ]);
            }

            return $customer->payments()->create([
                'business_date' => $date,
                'amount' => round($amount, 2),
                'payment_method' => $method,
                'reference' => $reference,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function deletePayment(CustomerPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->closings->assertOpen($payment->business_date, 'record');
            $payment->delete();
        });
    }

    public function balance(Customer $customer): float
    {
        return round(
            (float) $customer->opening_balance
            + (float) $customer->credits()->sum('amount')
            - (float) $customer->payments()->sum('amount'),
            2,
        );
    }

    /**
     * Statement: totals, aging of what is unpaid, open debts and a ledger
     * with a running balance.
     *
     * @return array{customer: Customer, credited: float, received: float, balance: float, available: ?float, overdue: float, aging: array<string, float>, debts: Collection<int, array<string, mixed>>, ledger: Collection<int, array<string, mixed>>, last_payment: ?CustomerPayment}
     */
    public function account(Customer $customer, ?Carbon $from = null, ?Carbon $to = null): array
    {
        // summaries() eager-loads these for every customer at once; a single account loads its own.
        $customer->loadMissing(self::accountRelations());
        $credits = $customer->credits;
        $payments = $customer->payments;
        $owedDeliveries = $customer->owedDeliveries;
        $openingDate = Carbon::parse($customer->created_at ?? BusinessClock::today())->startOfDay();

        $bills = [];

        if ((float) $customer->opening_balance > 0) {
            $bills[] = ['date' => $openingDate, 'due' => $openingDate->copy()->addDays(self::TERMS_DAYS), 'amount' => (float) $customer->opening_balance, 'label' => 'Opening balance', 'credit' => null];
        }

        foreach ($credits as $credit) {
            $bills[] = [
                'date' => $credit->business_date,
                'due' => $credit->business_date->copy()->addDays(self::TERMS_DAYS),
                'amount' => (float) $credit->amount,
                'label' => $this->describe($credit),
                'credit' => $credit,
            ];
        }

        $received = round((float) $payments->sum('amount'), 2);
        $debts = collect(Settlement::allocate($bills, $received));
        $today = BusinessClock::today();

        $aging = array_fill_keys(array_keys(self::AGING), 0.0);

        foreach ($debts->where('remaining', '>', 0) as $debt) {
            $age = (int) $debt['date']->diffInDays($today);

            foreach (self::AGING as $bucket => [$min, $max]) {
                if ($age >= $min && $age <= $max) {
                    $aging[$bucket] = round($aging[$bucket] + $debt['remaining'], 2);
                }
            }
        }

        $ledger = collect();

        if ((float) $customer->opening_balance != 0.0) {
            $ledger->push(['date' => $openingDate, 'at' => $openingDate, 'type' => 'opening', 'label' => 'Opening balance', 'detail' => null, 'debit' => (float) $customer->opening_balance, 'credit' => 0.0, 'model' => null]);
        }

        foreach ($credits as $credit) {
            $ledger->push([
                'date' => $credit->business_date,
                'at' => $credit->created_at,
                'type' => 'credit',
                'label' => 'Credit sale · '.($credit->fuelProduct?->name ?? 'Amount'),
                'detail' => collect([
                    $credit->quantity !== null ? number_format((float) $credit->quantity, 2).' '.$credit->fuelProduct?->unit->value.' @ Rs. '.number_format((float) $credit->rate, 2) : null,
                    $credit->vehicle_number,
                    $credit->slip_number ? 'Slip '.$credit->slip_number : null,
                    (float) $credit->owed_quantity > 0 ? number_format($credit->givenQuantity(), 2).' L given, '.number_format((float) $credit->owed_quantity, 2).' L owed' : null,
                ])->filter()->join(' · ') ?: null,
                'debit' => (float) $credit->amount,
                'credit' => 0.0,
                'model' => $credit,
            ]);
        }

        // Owed fuel given later: no money moves, shown so the slip's history is complete.
        foreach ($owedDeliveries as $delivery) {
            $ledger->push([
                'date' => $delivery->business_date,
                'at' => $delivery->created_at,
                'type' => 'owed',
                'label' => 'Owed fuel given · '.number_format((float) $delivery->quantity, 2).' L '.$delivery->fuelProduct?->name,
                'detail' => collect([
                    'Billed on '.$delivery->credit->business_date->format('d M Y'),
                    $delivery->credit->slip_number ? 'Slip '.$delivery->credit->slip_number : null,
                    $delivery->notes,
                ])->filter()->join(' · '),
                'debit' => 0.0,
                'credit' => 0.0,
                'model' => $delivery,
            ]);
        }

        foreach ($payments as $payment) {
            $ledger->push([
                'date' => $payment->business_date,
                'at' => $payment->created_at,
                'type' => 'payment',
                'label' => 'Payment received · '.$payment->payment_method->label(),
                'detail' => collect([$payment->reference, $payment->notes])->filter()->join(' · ') ?: null,
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
                'model' => $payment,
            ]);
        }

        $running = 0.0;
        $ledger = $ledger
            ->sortBy(fn (array $row) => $row['date']->toDateString().' '.$row['at']->format('H:i:s.u'))
            ->values()
            ->map(function (array $row) use (&$running) {
                $running = round($running + $row['debit'] - $row['credit'], 2);

                return [...$row, 'balance' => $running];
            });

        if ($from || $to) {
            $ledger = $ledger->filter(fn (array $row) => (! $from || $row['date']->gte($from)) && (! $to || $row['date']->lte($to)))->values();
        }

        $credited = round((float) $credits->sum('amount'), 2);
        $balance = round((float) $customer->opening_balance + $credited - $received, 2);

        return [
            'customer' => $customer,
            'credited' => $credited,
            'received' => $received,
            'balance' => $balance,
            'available' => $customer->credit_limit !== null ? round((float) $customer->credit_limit - $balance, 2) : null,
            'overdue' => round($debts->where('remaining', '>', 0)->filter(fn (array $debt) => $debt['due']->lt($today))->sum('remaining'), 2),
            'aging' => $aging,
            'debts' => $debts->where('remaining', '>', 0)->reverse()->values(),
            'ledger' => $ledger,
            'last_payment' => $payments->last(),
        ];
    }

    /**
     * Active customers with their balance, for pickers (two queries in total).
     *
     * @return Collection<int, array{id: int, name: string, vehicle: ?string, balance: float}>
     */
    public function pickerList(): Collection
    {
        $credited = CustomerCredit::query()->groupBy('customer_id')->selectRaw('customer_id, SUM(amount) as total')->pluck('total', 'customer_id');
        $received = CustomerPayment::query()->groupBy('customer_id')->selectRaw('customer_id, SUM(amount) as total')->pluck('total', 'customer_id');

        return Customer::query()->active()->orderBy('name')->get()->map(fn (Customer $customer) => [
            'id' => $customer->id,
            'name' => $customer->name,
            'vehicle' => $customer->vehicle_number,
            'balance' => round((float) $customer->opening_balance + (float) ($credited[$customer->id] ?? 0) - (float) ($received[$customer->id] ?? 0), 2),
        ])->values();
    }

    /**
     * Everything account() reads, in date order, so many accounts load in a few queries.
     *
     * @return array<string, \Closure>
     */
    private static function accountRelations(): array
    {
        $dated = fn ($query) => $query->orderBy('business_date')->orderBy('id');

        return [
            'credits' => fn ($query) => $dated($query)->with(['fuelProduct', 'recorder'])->withSum('owedDeliveries as owed_delivered_sum', 'quantity'),
            'payments' => fn ($query) => $dated($query)->with('recorder'),
            'owedDeliveries' => fn ($query) => $dated($query)->with(['credit', 'fuelProduct']),
        ];
    }

    /**
     * Index totals per customer.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function summaries(): Collection
    {
        return Customer::query()
            ->with(self::accountRelations())
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $customer) => collect($this->account($customer))->except(['debts', 'ledger'])->all());
    }

    /**
     * Credit given on a day; it is part of total sales but not in the drawer.
     */
    public static function creditOn(string $date): float
    {
        return round((float) CustomerCredit::query()->whereDate('business_date', $date)->sum('amount'), 2);
    }

    /**
     * Value of fuel billed on credit on a day but not given that day.
     * It is in the credit sales, but not in the nozzle sales.
     */
    public static function owedBilledOn(string $date): float
    {
        return round(CustomerCredit::query()
            ->whereDate('business_date', $date)
            ->where('owed_quantity', '>', 0)
            ->get(['owed_quantity', 'rate'])
            ->sum(fn (CustomerCredit $credit) => $credit->owedValue()), 2);
    }

    /**
     * Value of owed fuel given on a day against earlier slips.
     * It is in the nozzle sales, but brings no cash.
     */
    public static function owedGivenOn(string $date): float
    {
        return round((float) OwedFuelDelivery::query()->whereDate('business_date', $date)->sum('amount'), 2);
    }

    /**
     * Liters billed on a day but owed for later, and owed liters given on a day.
     *
     * @return array{billed: float, given: float}
     */
    public static function owedLitersOn(string $date): array
    {
        return [
            'billed' => round((float) CustomerCredit::query()->whereDate('business_date', $date)->sum('owed_quantity'), 2),
            'given' => round((float) OwedFuelDelivery::query()->whereDate('business_date', $date)->sum('quantity'), 2),
        ];
    }

    /**
     * Cash received from credit customers on a day, which goes into the drawer.
     */
    public static function cashReceivedOn(string $date): float
    {
        return round((float) CustomerPayment::query()
            ->whereDate('business_date', $date)
            ->where('payment_method', PaymentMethod::Cash->value)
            ->sum('amount'), 2);
    }

    public function describe(CustomerCredit $credit): string
    {
        $what = $credit->fuelProduct
            ? number_format((float) $credit->quantity, 2).' '.$credit->fuelProduct->unit->value.' '.$credit->fuelProduct->name
            : 'Credit';

        return $what.($credit->vehicle_number ? ' · '.$credit->vehicle_number : '');
    }

    /**
     * @throws ValidationException
     */
    private function assertWithinLimit(Customer $customer, float $amount, string $field = 'amount'): void
    {
        if ($customer->credit_limit === null) {
            return;
        }

        $balance = $this->balance($customer);
        $limit = (float) $customer->credit_limit;

        if ($balance + $amount > $limit + 0.005) {
            throw ValidationException::withMessages([
                $field => sprintf(
                    '%s would go over the credit limit of Rs. %s (owes Rs. %s, room for Rs. %s).',
                    $customer->name,
                    number_format($limit, 2),
                    number_format($balance, 2),
                    number_format(max(0, $limit - $balance), 2),
                ),
            ]);
        }
    }
}
