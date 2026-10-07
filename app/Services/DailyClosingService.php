<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ProductType;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\User;
use App\Support\BusinessClock;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyClosingService
{
    /**
     * Live figures for a business day, before or after it is closed.
     * Liters count fuel only; sales include counter items.
     *
     * Credit sales come from the debt register; cash received from credit
     * customers and cash paid to suppliers also pass through the drawer.
     *
     * @return array{date: string, total_liters: float, total_sales: float, fuel_sales: float, counter_sales: float, credit_sales: float, total_expenses: float, cash_expenses: float, customer_receipts: float, supplier_payments: float, is_closed: bool}
     */
    public function summary(DateTimeInterface|string $date): array
    {
        $date = $this->normalize($date);

        $sales = Sale::query()
            ->join('fuel_products', 'fuel_products.id', '=', 'sales.fuel_product_id')
            ->whereDate('sales.business_date', $date)
            ->selectRaw('COALESCE(SUM(CASE WHEN fuel_products.type = ? THEN sales.quantity ELSE 0 END), 0) as liters', [ProductType::Fuel->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN fuel_products.type = ? THEN sales.amount ELSE 0 END), 0) as fuel_amount', [ProductType::Fuel->value])
            ->selectRaw('COALESCE(SUM(sales.amount), 0) as amount')
            ->toBase()
            ->first();

        $expenses = Expense::query()
            ->whereDate('business_date', $date)
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN payment_method = ? THEN amount ELSE 0 END), 0) as cash', [PaymentMethod::Cash->value])
            ->toBase()
            ->first();

        $total = round((float) $sales->amount, 2);
        $fuel = round((float) $sales->fuel_amount, 2);

        return [
            'date' => $date,
            'total_liters' => round((float) $sales->liters, 2),
            'total_sales' => $total,
            'fuel_sales' => $fuel,
            'counter_sales' => round($total - $fuel, 2),
            'credit_sales' => CreditService::creditOn($date),
            'total_expenses' => round((float) $expenses->total, 2),
            'cash_expenses' => round((float) $expenses->cash, 2),
            'customer_receipts' => CreditService::cashReceivedOn($date),
            'supplier_payments' => SupplierService::cashPaidOn($date),
            'is_closed' => $this->isClosed($date),
        ];
    }

    /**
     * Closes and locks a business day with a cash reconciliation snapshot.
     *
     * @throws ValidationException
     */
    public function close(
        DateTimeInterface|string $date,
        float $openingCash,
        float $actualCash,
        float $cardSales = 0,
        ?User $closedBy = null,
        ?string $notes = null,
    ): DailyClosing {
        $date = $this->normalize($date);

        if ($date > BusinessClock::today()->toDateString()) {
            throw ValidationException::withMessages(['business_date' => 'A future day cannot be closed.']);
        }

        return DB::transaction(function () use ($date, $openingCash, $actualCash, $cardSales, $closedBy, $notes) {
            $this->assertOpen($date);

            return DailyClosing::create([
                'business_date' => $date,
                ...$this->reconcile($date, $openingCash, $actualCash, $cardSales),
                'notes' => $notes,
                'closed_by' => $closedBy?->id,
            ]);
        });
    }

    /**
     * Corrects the cash count of a closed day. Sales and expense totals are
     * taken again from the records, and the cash difference is recalculated.
     *
     * @throws ValidationException
     */
    public function updateCash(
        DailyClosing $closing,
        float $openingCash,
        float $actualCash,
        float $cardSales = 0,
        ?string $notes = null,
    ): DailyClosing {
        BusinessClock::assertTrustworthy();

        return DB::transaction(function () use ($closing, $openingCash, $actualCash, $cardSales, $notes) {
            $closing = DailyClosing::query()->lockForUpdate()->findOrFail($closing->id);

            $closing->update([
                ...$this->reconcile($closing->business_date->toDateString(), $openingCash, $actualCash, $cardSales),
                'notes' => $notes,
            ]);

            return $closing;
        });
    }

    /**
     * Unlocks a closed day by deleting its closing, so its records can be
     * corrected. The day is closed again from the daily closing page.
     *
     * @throws ValidationException
     */
    public function reopen(DailyClosing $closing): void
    {
        BusinessClock::assertTrustworthy();

        $closing->delete();
    }

    /**
     * @return array<string, float>
     *
     * @throws ValidationException
     */
    private function reconcile(string $date, float $openingCash, float $actualCash, float $cardSales): array
    {
        $summary = $this->summary($date);
        $creditSales = $summary['credit_sales'];
        $cashSales = round($summary['total_sales'] - $cardSales - $creditSales, 2);

        if ($cashSales < 0) {
            throw ValidationException::withMessages([
                'card_sales' => sprintf(
                    'Card sales plus credit sales (Rs. %s from the debt register) cannot exceed total sales of Rs. %s.',
                    number_format($creditSales, 2),
                    number_format($summary['total_sales'], 2),
                ),
            ]);
        }

        $expectedCash = round(
            $openingCash + $cashSales + $summary['customer_receipts'] - $summary['cash_expenses'] - $summary['supplier_payments'],
            2,
        );

        return [
            'total_liters' => $summary['total_liters'],
            'total_sales' => $summary['total_sales'],
            'card_sales' => round($cardSales, 2),
            'credit_sales' => round($creditSales, 2),
            'cash_sales' => $cashSales,
            'total_expenses' => $summary['total_expenses'],
            'cash_expenses' => $summary['cash_expenses'],
            'customer_receipts' => $summary['customer_receipts'],
            'supplier_payments' => $summary['supplier_payments'],
            'opening_cash' => round($openingCash, 2),
            'expected_cash' => $expectedCash,
            'actual_cash' => round($actualCash, 2),
            'difference' => round($actualCash - $expectedCash, 2),
        ];
    }

    public function isClosed(DateTimeInterface|string $date): bool
    {
        return DailyClosing::query()->whereDate('business_date', $this->normalize($date))->exists();
    }

    /**
     * Guards every write: the server clock must be trustworthy and the day open.
     *
     * @throws ValidationException
     */
    public function assertOpen(DateTimeInterface|string $date, string $field = 'business_date'): void
    {
        BusinessClock::assertTrustworthy();

        $date = $this->normalize($date);

        if ($this->isClosed($date)) {
            throw ValidationException::withMessages([
                $field => 'The business day '.Carbon::parse($date)->format('d M Y').' is already closed.',
            ]);
        }
    }

    private function normalize(DateTimeInterface|string $date): string
    {
        return Carbon::parse($date)->toDateString();
    }
}
