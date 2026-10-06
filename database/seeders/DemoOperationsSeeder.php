<?php

namespace Database\Seeders;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\Shift;
use App\Models\FuelProduct;
use App\Models\Nozzle;
use App\Models\User;
use App\Services\DailyClosingService;
use App\Services\ExpenseService;
use App\Services\FuelPriceService;
use App\Services\FuelStockService;
use App\Services\MeterReadingService;
use App\Services\SaleService;
use App\Support\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Generates ten days of consistent demo activity. Every figure the owner
 * sees is calculated by the real services from meter readings, counter
 * sales, purchases and expenses, so the numbers always agree.
 *
 * Past days have morning, evening and night shifts and are closed. Today
 * has the morning shift recorded and is left open for the live demo.
 */
class DemoOperationsSeeder extends Seeder
{
    private const DAYS = 10;

    /** Pump 01 · Nozzle 1 ends on this reading, matching the demo script. */
    private const DEMO_NOZZLE_FINAL_METER = 125430.50;

    /** Petrol moves from its opening price to this on the given day. */
    private const PETROL_NEW_PRICE = 280.0;

    private const PETROL_PRICE_CHANGE_DAY = 4;

    /** @var array<string, array{min: int, max: int}> Liters per nozzle per full day. */
    private const DAILY_LITERS = [
        'petrol' => ['min' => 700, 'max' => 1100],
        'diesel' => ['min' => 800, 'max' => 1300],
        'hi-octane' => ['min' => 150, 'max' => 300],
    ];

    /** @var array<string, float> Share of a full day sold in each shift. */
    private const SHIFT_SHARE = ['morning' => 0.45, 'evening' => 0.40, 'night' => 0.15];

    /** @var array<string, array{reorder_below: int, quantity: int, margin: float}> */
    private const RESTOCK = [
        'petrol' => ['reorder_below' => 5000, 'quantity' => 10000, 'margin' => 9.50],
        'diesel' => ['reorder_below' => 4000, 'quantity' => 8000, 'margin' => 9.00],
        'hi-octane' => ['reorder_below' => 1000, 'quantity' => 3000, 'margin' => 12.00],
        'engine-oil-1l' => ['reorder_below' => 15, 'quantity' => 24, 'margin' => 250.00],
        'engine-oil-4l' => ['reorder_below' => 8, 'quantity' => 12, 'margin' => 900.00],
    ];

    /** @var array<string, array{min: int, max: int}> Units sold per day at the counter. */
    private const COUNTER_SALES = [
        'engine-oil-1l' => ['min' => 1, 'max' => 4],
        'engine-oil-4l' => ['min' => 0, 'max' => 2],
        'brake-oil' => ['min' => 0, 'max' => 2],
        'coolant' => ['min' => 0, 'max' => 1],
    ];

    private Randomizer $random;

    public function __construct(
        private MeterReadingService $readings,
        private DailyClosingService $closings,
        private FuelStockService $stock,
        private ExpenseService $expenses,
        private FuelPriceService $prices,
        private SaleService $sales,
    ) {
        $this->random = new Randomizer(new Mt19937(2026));
    }

    public function run(): void
    {
        $staff = User::firstWhere('email', 'staff@alnoor.com');
        $owner = User::firstWhere('email', 'owner@alnoor.com');
        $nozzles = Nozzle::query()->with(['pump', 'fuelProduct'])->orderBy('pump_id')->orderBy('number')->get();
        $products = FuelProduct::query()->get()->keyBy('code');
        $days = collect(range(self::DAYS - 1, 0))->map(fn (int $ago) => today()->subDays($ago));

        $plan = $this->planLiters($nozzles, $days);
        $this->setStartingMeters($nozzles, $plan);

        $stock = $products->mapWithKeys(fn (FuelProduct $p) => [$p->code => (float) $p->opening_stock])->all();

        DB::transaction(function () use ($days, $nozzles, $products, $plan, $staff, $owner, &$stock) {
            foreach ($products as $product) {
                $this->prices->record($product, (float) $product->selling_price, $days->first()->copy()->startOfDay(), $owner, 'Opening price');
            }

            foreach ($days as $index => $day) {
                $isToday = $day->isToday();

                if ($index === self::PETROL_PRICE_CHANGE_DAY) {
                    $this->prices->record($products['petrol'], self::PETROL_NEW_PRICE, $day->copy()->startOfDay(), $owner, 'Government price notification');
                }

                $this->restock($day, $products, $stock, $staff);

                foreach ($this->shiftsFor($day) as $shift) {
                    foreach ($nozzles as $nozzle) {
                        $liters = $plan[$nozzle->id][$index][$shift->value];
                        $opening = (float) $nozzle->fresh()->current_meter;

                        $this->readings->record(
                            nozzle: $nozzle,
                            date: $day,
                            shift: $shift,
                            openingMeter: $opening,
                            closingMeter: round($opening + $liters, 2),
                            recordedBy: $staff,
                        );

                        $stock[$nozzle->fuelProduct->code] -= $liters;
                    }
                }

                $this->recordCounterSales($day, $products, $stock, $staff, $isToday);
                $this->recordExpenses($day, $staff, $isToday);

                if (! $isToday) {
                    $this->closeDay($day, $staff);
                }
            }
        });
    }

    /**
     * @return list<Shift>
     */
    private function shiftsFor(Carbon $day): array
    {
        return $day->isToday() ? [Shift::Morning] : Shift::cases();
    }

    /**
     * Pre-generates liters per nozzle, day and shift so starting meters can
     * be worked out backwards.
     *
     * @param  Collection<int, Nozzle>  $nozzles
     * @param  Collection<int, Carbon>  $days
     * @return array<int, list<array<string, float>>>
     */
    private function planLiters(Collection $nozzles, Collection $days): array
    {
        $plan = [];

        foreach ($nozzles as $nozzle) {
            $range = self::DAILY_LITERS[$nozzle->fuelProduct->code];

            foreach ($days as $day) {
                $full = $this->random->getInt($range['min'] * 100, $range['max'] * 100) / 100;
                $shares = [];

                foreach ($this->shiftsFor($day) as $shift) {
                    $shares[$shift->value] = round($full * self::SHIFT_SHARE[$shift->value], 2);
                }

                $plan[$nozzle->id][] = $shares;
            }
        }

        return $plan;
    }

    /**
     * @param  Collection<int, Nozzle>  $nozzles
     * @param  array<int, list<array<string, float>>>  $plan
     */
    private function setStartingMeters(Collection $nozzles, array $plan): void
    {
        foreach ($nozzles as $nozzle) {
            $finalMeter = $nozzle->pump->name === 'Pump 01' && $nozzle->number === 1
                ? self::DEMO_NOZZLE_FINAL_METER
                : $this->random->getInt(4_000_000, 20_000_000) / 100;

            $total = collect($plan[$nozzle->id])->sum(fn (array $shifts) => array_sum($shifts));

            $nozzle->update(['current_meter' => round($finalMeter - $total, 2)]);
        }
    }

    /**
     * Orders stock whenever the morning level is below the reorder point.
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

            $product = $products[$code]->fresh();
            $isFuel = $product->isMetered();

            $this->stock->recordPurchase(
                product: $product,
                date: $day,
                quantity: $rule['quantity'],
                rate: (float) $product->selling_price - $rule['margin'],
                supplierName: $isFuel ? 'Bahawalpur Oil Depot' : 'City Lubricants Traders',
                invoiceNumber: 'INV-'.$day->format('md').'-'.strtoupper(substr($code, 0, 2)),
                recordedBy: $staff,
            );

            $stock[$code] += $rule['quantity'];
        }
    }

    /**
     * @param  Collection<string, FuelProduct>  $products
     * @param  array<string, float>  $stock
     */
    private function recordCounterSales(Carbon $day, Collection $products, array &$stock, ?User $staff, bool $isToday): void
    {
        foreach (self::COUNTER_SALES as $code => $range) {
            $quantity = $this->random->getInt($range['min'], $range['max']);

            if ($isToday) {
                $quantity = min($quantity, 1);
            }

            if ($quantity === 0 || $stock[$code] < $quantity) {
                continue;
            }

            $this->sales->recordCounterSale($products[$code], $day, $quantity, $staff);
            $stock[$code] -= $quantity;
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
            $this->expenses->record(
                date: $day,
                category: $category,
                description: $description,
                amount: $this->roundTo($this->random->getInt($min, $max), 50),
                paymentMethod: $method,
                recordedBy: $staff,
            );
        }
    }

    private function closeDay(Carbon $day, ?User $staff): void
    {
        $summary = $this->closings->summary($day);
        $totalSales = $summary['total_sales'];
        $openingCash = Station::defaultOpeningCash();

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
