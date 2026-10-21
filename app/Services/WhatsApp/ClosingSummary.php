<?php

namespace App\Services\WhatsApp;

use App\Enums\ProductType;
use App\Models\DailyClosing;
use App\Services\FuelStockService;
use App\Support\Format;
use App\Support\Station;
use Illuminate\Support\Collection;

/**
 * The owner's end-of-day WhatsApp summary of a closed day: fuel sold, money
 * taken, cash counted and fuel left in stock.
 */
class ClosingSummary
{
    public function __construct(private FuelStockService $stock) {}

    /**
     * Facts of the day, already formatted for reading.
     *
     * @return array{station: string, date: string, liters: string, liters_by_fuel: string, sales: string, split: string, expenses: string, cash: string, stock_left: string}
     */
    public function facts(DailyClosing $closing): array
    {
        $fuel = $this->stock->summary($closing->business_date)
            ->filter(fn (array $row) => $row['product']->type === ProductType::Fuel);

        $difference = (float) $closing->difference;
        $cashState = abs($difference) < 0.005
            ? 'balanced'
            : ($difference < 0 ? 'short '.Format::money(abs($difference)) : 'excess '.Format::money($difference));

        return [
            'station' => Station::name(),
            'date' => $closing->business_date->format('D d M Y'),
            'liters' => Format::liters($closing->total_liters),
            'liters_by_fuel' => $this->list($fuel, 'sold'),
            'sales' => Format::money($closing->total_sales),
            'split' => sprintf('Cash %s, Card %s, Credit %s', Format::money($closing->cash_sales), Format::money($closing->card_sales), Format::money($closing->credit_sales))
                .((float) $closing->relief_amount > 0 ? ', Fuel relief '.Format::money($closing->relief_amount) : ''),
            'expenses' => Format::money($closing->total_expenses),
            'cash' => Format::money($closing->actual_cash).' counted, '.$cashState,
            'stock_left' => $this->list($fuel, 'closing'),
        ];
    }

    /**
     * Free text message (works when the owner messaged the business number in the last 24 hours).
     */
    public function text(DailyClosing $closing): string
    {
        $f = $this->facts($closing);

        return implode("\n", [
            "*{$f['station']}*",
            "Day closed: {$f['date']}",
            '',
            "⛽ Fuel sold: *{$f['liters']}*",
            $f['liters_by_fuel'],
            "💰 Total sales: *{$f['sales']}*",
            $f['split'],
            "🧾 Expenses: {$f['expenses']}",
            "💵 Cash: {$f['cash']}",
            "🛢 Stock left: {$f['stock_left']}",
            '',
            '— FuelTrack',
        ]);
    }

    /**
     * Values for the approved template's {{1}}…{{6}}, in order. WhatsApp does
     * not allow line breaks inside template values, so lists use commas.
     *
     * @return list<string>
     */
    public function templateValues(DailyClosing $closing): array
    {
        $f = $this->facts($closing);

        return [
            $f['station'],
            $f['date'],
            "{$f['liters']} ({$f['liters_by_fuel']})",
            "{$f['sales']} ({$f['split']})",
            $f['cash'],
            $f['stock_left'],
        ];
    }

    /**
     * Template body to create in Meta's WhatsApp Manager, matching templateValues().
     */
    public static function templateBody(): string
    {
        return implode("\n", [
            'Day closed at {{1}} for {{2}}.',
            '',
            'Fuel sold: {{3}}',
            'Total sales: {{4}}',
            'Cash: {{5}}',
            'Stock left: {{6}}',
            '',
            'Sent by FuelTrack',
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function list(Collection $rows, string $field): string
    {
        return $rows->map(fn (array $row) => $row['product']->name.' '.Format::liters($row[$field]))->join(', ') ?: '—';
    }
}
