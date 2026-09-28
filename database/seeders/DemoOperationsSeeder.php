<?php

namespace Database\Seeders;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\FuelProduct;
use App\Models\FuelPurchase;
use App\Models\Nozzle;
use App\Models\Setting;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\MeterReadingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Generates ten days of consistent demo activity. Every figure the
 * owner sees is calculated by the real services from meter readings,
 * purchases and expenses, so the numbers always agree with each other.
 *
 * The last nine days are closed. Today has a morning shift recorded
 * and is left open for the live demo (evening reading + daily closing).
 */
class DemoOperationsSeeder extends Seeder
{
    private const DAYS = 10;

    /** Pump 01 · Nozzle 1 ends on this reading, matching the demo script. */
    private const DEMO_NOZZLE_FINAL_METER = 125430.50;

    /** Petrol cost Rs. 275 for the first few days, then Rs. 280. */
    private const PETROL_OLD_PRICE = 275.0;

    private const PETROL_PRICE_CHANGE_DAY = 4;

    /** @var array<string, array{min: int, max: int}> Liters per nozzle per full day. */
    private const DAILY_LITERS = [
        'petrol' => ['min' => 700, 'max' => 1100],
        'diesel' => ['min' => 800, 'max' => 1300],
        'hi-octane' => ['min' => 150, 'max' => 300],
    ];

    /** @var array<string, array{reorder_below: int, quantity: int, margin: float}> */
    private const RESTOCK = [
        'petrol' => ['reorder_below' => 5000, 'quantity' => 10000, 'margin' => 9.50],
        'diesel' => ['reorder_below' => 4000, 'quantity' => 8000, 'margin' => 9.00],
        'hi-octane' => ['reorder_below' => 1000, 'quantity' => 3000, 'margin' => 12.00],
    ];

    private Randomizer $random;

    public function __construct(
        private MeterReadingService $readings,
        private DailyClosingService $closings,
    ) {
        $this->random = new Randomizer(new Mt19937(2026));
    }

    public function run(): void
    {
        $staff = User::firstWhere('email', 'staff@alnoor.com');
        $nozzles = Nozzle::query()->with(['pump', 'fuelProduct'])->orderBy('pump_id')->orderBy('number')->get();
        $products = FuelProduct::query()->get()->keyBy('code');
        $days = collect(range(self::DAYS - 1, 0))->map(fn (int $ago) => today()->subDays($ago));

        $plan = $this->planLiters($nozzles, $days);
        $this->setStartingMeters($nozzles, $plan);

        $stock = $products->mapWithKeys(fn (FuelProduct $p) => [$p->code => (float) $p->opening_stock])->all();

        DB::transaction(function () use ($days, $nozzles, $products, $plan, $staff, &$stock) {
            foreach ($days as $index => $day) {
                $isToday = $day->isToday();

                $this->restock($day, $products, $stock, $staff);

                foreach ($nozzles as $nozzle) {
                    $liters = $plan[$nozzle->id][$index];
                    $code = $nozzle->fuelProduct->code;
                    $opening = (float) $nozzle->fresh()->current_meter;

                    $this->readings->record(
                        nozzle: $nozzle,
                        date: $day,
                        openingMeter: $opening,
                        closingMeter: round($opening + $liters, 2),
                        recordedBy: $staff,
                        rate: $code === 'petrol' && $index < self::PETROL_PRICE_CHANGE_DAY ? self::PETROL_OLD_PRICE : null,
                        notes: $isToday ? 'Morning shift' : null,
                    );

                    $stock[$code] -= $liters;
                }

                $this->recordExpenses($day, $staff, $isToday);

                if (! $isToday) {
                    $this->closeDay($day, $staff);
                }
            }
        });
    }

    /**
     * Pre-generates liters so starting meters can be worked out backwards.
     *
     * @param  Collection<int, Nozzle>  $nozzles
     * @param  Collection<int, Carbon>  $days
     * @return array<int, list<float>>
     */
    private function planLiters(Collection $nozzles, Collection $days): array
    {
        $plan = [];

        foreach ($nozzles as $nozzle) {
            $range = self::DAILY_LITERS[$nozzle->fuelProduct->code];

            foreach ($days as $day) {
                $liters = $this->random->getInt($range['min'] * 100, $range['max'] * 100) / 100;

                // Today only has the morning shift (~40% of a full day).
                $plan[$nozzle->id][] = $day->isToday() ? round($liters * 0.4, 2) : $liters;
            }
        }

        return $plan;
    }

    /**
     * @param  Collection<int, Nozzle>  $nozzles
     * @param  array<int, list<float>>  $plan
     */
    private function setStartingMeters(Collection $nozzles, array $plan): void
    {
        foreach ($nozzles as $nozzle) {
            $finalMeter = $nozzle->pump->name === 'Pump 01' && $nozzle->number === 1
                ? self::DEMO_NOZZLE_FINAL_METER
                : $this->random->getInt(4_000_000, 20_000_000) / 100;

            $nozzle->update(['current_meter' => round($finalMeter - array_sum($plan[$nozzle->id]), 2)]);
        }
    }

    /**
     * Orders a tanker whenever the morning stock is below the reorder level.
     *
     * @param  Collection<string, FuelProduct>  $products
     * @param  array<string, float>  $stock
     */
    private function restock(Carbon $day, Collection $products, array &$stock, ?User $staff): void
    {
        foreach (self::RESTOCK as $code => $rule) {
            if ($stock[$code] >= $rule['reorder_below']) {
                continue;
            }

            $rate = (float) $products[$code]->selling_price - $rule['margin'];

            FuelPurchase::create([
                'fuel_product_id' => $products[$code]->id,
                'business_date' => $day,
                'quantity' => $rule['quantity'],
                'rate' => $rate,
                'amount' => round($rule['quantity'] * $rate, 2),
                'supplier_name' => 'Bahawalpur Oil Depot',
                'invoice_number' => 'INV-'.$day->format('md').'-'.strtoupper(substr($code, 0, 2)),
                'recorded_by' => $staff?->id,
            ]);

            $stock[$code] += $rule['quantity'];
        }
    }

    private function recordExpenses(Carbon $day, ?User $staff, bool $isToday): void
    {
        $expenses = [
            [ExpenseCategory::Generator, 'Generator diesel', 3000, 6000, PaymentMethod::Cash],
            [ExpenseCategory::Cleaning, 'Forecourt cleaning', 1500, 3000, PaymentMethod::Cash],
            [ExpenseCategory::Office, 'Tea and refreshments', 800, 1500, PaymentMethod::Cash],
        ];

        if ($day->dayOfWeek === Carbon::MONDAY) {
            $expenses[] = [ExpenseCategory::Maintenance, 'Dispenser servicing', 6000, 9000, PaymentMethod::Cash];
        }

        if ($day->day === 1 || $day->equalTo(today()->subDays(self::DAYS - 1))) {
            $expenses[] = [ExpenseCategory::Electricity, 'Electricity bill', 14000, 16000, PaymentMethod::Bank];
            $expenses[] = [ExpenseCategory::Security, 'Security guard salary', 25000, 25000, PaymentMethod::Bank];
        }

        // Keep today light so the live demo expense stands out.
        foreach ($isToday ? array_slice($expenses, 0, 1) : $expenses as [$category, $description, $min, $max, $method]) {
            Expense::create([
                'business_date' => $day,
                'category' => $category,
                'description' => $description,
                'amount' => $this->roundTo($this->random->getInt($min, $max), 50),
                'payment_method' => $method,
                'recorded_by' => $staff?->id,
            ]);
        }
    }

    private function closeDay(Carbon $day, ?User $staff): void
    {
        $summary = $this->closings->summary($day);
        $totalSales = $summary['total_sales'];
        $openingCash = (float) Setting::get('default_opening_cash', '100000');

        $cardSales = $this->roundTo($totalSales * $this->random->getInt(12, 20) / 100, 100);
        $creditSales = $this->roundTo($totalSales * $this->random->getInt(4, 8) / 100, 100);

        $expectedCash = $openingCash + ($totalSales - $cardSales - $creditSales) - $summary['cash_expenses'];

        // Mostly balanced, with the occasional small shortage or excess.
        $difference = [0, 0, 0, 0, 0, -500, -2000, 300][$this->random->getInt(0, 7)];

        $this->closings->close(
            date: $day,
            openingCash: $openingCash,
            actualCash: round($expectedCash + $difference, 2),
            cardSales: $cardSales,
            creditSales: $creditSales,
            closedBy: $staff,
            notes: $difference < 0 ? 'Cash short, noted for follow-up.' : null,
        );
    }

    private function roundTo(float|int $value, int $step): float
    {
        return (float) (round($value / $step) * $step);
    }
}
