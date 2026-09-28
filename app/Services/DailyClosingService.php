<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\DailyClosing;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyClosingService
{
    /**
     * Live figures for a business day, before or after it is closed.
     *
     * @return array{date: string, total_liters: float, total_sales: float, total_expenses: float, cash_expenses: float, is_closed: bool}
     */
    public function summary(DateTimeInterface|string $date): array
    {
        $date = $this->normalize($date);

        $sales = Sale::query()
            ->whereDate('business_date', $date)
            ->selectRaw('COALESCE(SUM(liters), 0) as liters, COALESCE(SUM(amount), 0) as amount')
            ->first();

        $expenses = Expense::query()
            ->whereDate('business_date', $date)
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->selectRaw('COALESCE(SUM(CASE WHEN payment_method = ? THEN amount ELSE 0 END), 0) as cash', [PaymentMethod::Cash->value])
            ->first();

        return [
            'date' => $date,
            'total_liters' => round((float) $sales->liters, 2),
            'total_sales' => round((float) $sales->amount, 2),
            'total_expenses' => round((float) $expenses->total, 2),
            'cash_expenses' => round((float) $expenses->cash, 2),
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
        float $creditSales = 0,
        ?User $closedBy = null,
        ?string $notes = null,
    ): DailyClosing {
        $date = $this->normalize($date);

        return DB::transaction(function () use ($date, $openingCash, $actualCash, $cardSales, $creditSales, $closedBy, $notes) {
            $this->assertOpen($date);

            $summary = $this->summary($date);
            $cashSales = round($summary['total_sales'] - $cardSales - $creditSales, 2);

            if ($cashSales < 0) {
                throw ValidationException::withMessages([
                    'card_sales' => 'Card and credit sales cannot exceed total sales of Rs. '.number_format($summary['total_sales'], 2).'.',
                ]);
            }

            $expectedCash = round($openingCash + $cashSales - $summary['cash_expenses'], 2);

            return DailyClosing::create([
                'business_date' => $date,
                'total_liters' => $summary['total_liters'],
                'total_sales' => $summary['total_sales'],
                'card_sales' => round($cardSales, 2),
                'credit_sales' => round($creditSales, 2),
                'cash_sales' => $cashSales,
                'total_expenses' => $summary['total_expenses'],
                'cash_expenses' => $summary['cash_expenses'],
                'opening_cash' => round($openingCash, 2),
                'expected_cash' => $expectedCash,
                'actual_cash' => round($actualCash, 2),
                'difference' => round($actualCash - $expectedCash, 2),
                'notes' => $notes,
                'closed_by' => $closedBy?->id,
            ]);
        });
    }

    public function isClosed(DateTimeInterface|string $date): bool
    {
        return DailyClosing::query()->whereDate('business_date', $this->normalize($date))->exists();
    }

    /**
     * Guards every write against a day that has already been closed.
     *
     * @throws ValidationException
     */
    public function assertOpen(DateTimeInterface|string $date, string $field = 'business_date'): void
    {
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
