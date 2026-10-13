<?php

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\PaymentMethod;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\Settlement;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payables: what the station owes each supplier is
 * opening balance + purchases − payments. Payments settle the oldest bills
 * first, which drives each bill's paid / partial / due / overdue status.
 */
class SupplierService
{
    /** Bills due within this many days are flagged "due soon". */
    public const DUE_SOON_DAYS = 7;

    public function __construct(private DailyClosingService $closings) {}

    /**
     * @throws ValidationException
     */
    public function recordPayment(
        Supplier $supplier,
        float $amount,
        PaymentMethod $method = PaymentMethod::Cash,
        ?User $recordedBy = null,
        ?string $reference = null,
        ?string $notes = null,
        DateTimeInterface|string|null $date = null,
    ): SupplierPayment {
        $date = Carbon::parse($date ?? BusinessClock::today())->toDateString();

        return DB::transaction(function () use ($supplier, $amount, $method, $recordedBy, $reference, $notes, $date) {
            // Cash leaves the drawer, so it needs an open day. Bank and cheque
            // payments do not change the cash count and are allowed any time.
            BusinessClock::assertTrustworthy();

            if ($method === PaymentMethod::Cash && $this->closings->isClosed($date)) {
                $message = 'The business day '.Carbon::parse($date)->format('d M Y').' is closed, so no cash can leave the drawer. Pay by bank or cheque, or record the cash payment tomorrow.';

                throw ValidationException::withMessages(['amount' => $message, 'paid_now' => $message]);
            }
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);

            $balance = $this->balance($supplier);

            if ($amount > $balance + 0.005) {
                throw ValidationException::withMessages([
                    'amount' => $balance > 0
                        ? 'Only Rs. '.number_format($balance, 2).' is payable to '.$supplier->name.'.'
                        : 'Nothing is payable to '.$supplier->name.'.',
                ]);
            }

            return $supplier->payments()->create([
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
    public function deletePayment(SupplierPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->closings->assertOpen($payment->business_date, 'record');
            $payment->delete();
        });
    }

    public function balance(Supplier $supplier): float
    {
        $purchased = (float) $supplier->purchases()->sum('amount');
        $paid = (float) $supplier->payments()->sum('amount');

        return round((float) $supplier->opening_balance + $purchased - $paid, 2);
    }

    /**
     * Full account: totals, every bill with its settlement status, and a
     * ledger with a running balance.
     *
     * @return array{supplier: Supplier, purchased: float, paid: float, balance: float, overdue: float, due_soon: float, next_due: ?Carbon, bills: Collection<int, array<string, mixed>>, ledger: Collection<int, array<string, mixed>>}
     */
    public function account(Supplier $supplier): array
    {
        $purchases = $supplier->purchases()
            ->with(['fuelProduct', 'tank'])
            ->orderBy('business_date')
            ->orderBy('id')
            ->get();

        $payments = $supplier->payments()->with('recorder')->orderBy('business_date')->orderBy('id')->get();

        $openingDate = Carbon::parse($supplier->created_at ?? BusinessClock::today())->startOfDay();
        $bills = [];

        if ((float) $supplier->opening_balance > 0) {
            $bills[] = ['date' => $openingDate, 'due' => $openingDate, 'amount' => (float) $supplier->opening_balance, 'label' => 'Opening balance', 'purchase' => null];
        }

        foreach ($purchases as $purchase) {
            $bills[] = [
                'date' => $purchase->business_date,
                'due' => $purchase->due_date ?? $purchase->business_date,
                'amount' => (float) $purchase->amount,
                'label' => $purchase->fuelProduct->name.($purchase->invoice_number ? ' · Inv. '.$purchase->invoice_number : ''),
                'purchase' => $purchase,
            ];
        }

        $paid = round((float) $payments->sum('amount'), 2);
        $bills = collect(Settlement::allocate($bills, $paid));

        $ledger = collect();

        if ((float) $supplier->opening_balance != 0.0) {
            $ledger->push(['date' => $openingDate, 'at' => $openingDate, 'type' => 'opening', 'label' => 'Opening balance', 'detail' => null, 'debit' => (float) $supplier->opening_balance, 'credit' => 0.0, 'model' => null]);
        }

        foreach ($purchases as $purchase) {
            $ledger->push([
                'date' => $purchase->business_date,
                'at' => $purchase->created_at,
                'type' => 'purchase',
                'label' => 'Purchase · '.$purchase->fuelProduct->name,
                'detail' => collect([number_format((float) $purchase->quantity, 2).' '.$purchase->fuelProduct->unit->value.' @ Rs. '.number_format((float) $purchase->rate, 2), $purchase->invoice_number ? 'Inv. '.$purchase->invoice_number : null, $purchase->tank?->name])->filter()->join(' · '),
                'debit' => (float) $purchase->amount,
                'credit' => 0.0,
                'model' => $purchase,
            ]);
        }

        foreach ($payments as $payment) {
            $ledger->push([
                'date' => $payment->business_date,
                'at' => $payment->created_at,
                'type' => 'payment',
                'label' => 'Payment · '.$payment->payment_method->label(),
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

        $today = BusinessClock::today();
        $open = $bills->where('remaining', '>', 0);
        $purchased = round((float) $purchases->sum('amount'), 2);

        return [
            'supplier' => $supplier,
            'purchased' => $purchased,
            'paid' => $paid,
            'balance' => round((float) $supplier->opening_balance + $purchased - $paid, 2),
            'overdue' => round($open->where('status', BillStatus::Overdue)->sum('remaining'), 2),
            'due_soon' => round($open->filter(fn (array $bill) => $bill['status'] !== BillStatus::Overdue && $bill['due']->lte($today->copy()->addDays(self::DUE_SOON_DAYS)))->sum('remaining'), 2),
            'next_due' => $open->where('status', '!=', BillStatus::Overdue)->sortBy(fn (array $bill) => $bill['due']->timestamp)->first()['due'] ?? null,
            'bills' => $bills->reverse()->values(),
            'ledger' => $ledger,
        ];
    }

    /**
     * Every open bill across suppliers, soonest due first: the payment schedule.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function schedule(): Collection
    {
        return Supplier::query()->orderBy('name')->get()
            ->flatMap(fn (Supplier $supplier) => $this->account($supplier)['bills']
                ->where('remaining', '>', 0)
                ->map(fn (array $bill) => [...$bill, 'supplier' => $supplier]))
            ->sortBy(fn (array $bill) => $bill['due']->timestamp)
            ->values();
    }

    /**
     * Index totals per supplier.
     *
     * @return Collection<int, array{supplier: Supplier, purchased: float, paid: float, balance: float, overdue: float, due_soon: float, next_due: ?Carbon}>
     */
    public function summaries(): Collection
    {
        return Supplier::query()
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => collect($this->account($supplier))->except(['bills', 'ledger'])->all());
    }

    /**
     * Due date for a new bill from the supplier's credit terms.
     */
    public function dueDateFor(?Supplier $supplier, DateTimeInterface|string $date): Carbon
    {
        return Carbon::parse($date)->startOfDay()->addDays($supplier?->payment_terms_days ?? 0);
    }

    /**
     * Cash paid to suppliers on a day, which leaves the cash drawer.
     */
    public static function cashPaidOn(string $date): float
    {
        return round((float) SupplierPayment::query()
            ->whereDate('business_date', $date)
            ->where('payment_method', PaymentMethod::Cash->value)
            ->sum('amount'), 2);
    }
}
