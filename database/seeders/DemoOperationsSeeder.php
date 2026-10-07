<?php

namespace Database\Seeders;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Enums\Shift;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\FuelProduct;
use App\Models\Nozzle;
use App\Models\SalarySlip;
use App\Models\Supplier;
use App\Models\Tank;
use App\Models\User;
use App\Services\CreditService;
use App\Services\DailyClosingService;
use App\Services\ExpenseService;
use App\Services\FuelPriceService;
use App\Services\FuelStockService;
use App\Services\MeterReadingService;
use App\Services\PayrollService;
use App\Services\SaleService;
use App\Services\SupplierService;
use App\Services\TankService;
use App\Support\Station;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Generates ten days of consistent demo activity. Every figure the owner
 * sees is calculated by the real services from meter readings, counter
 * sales, purchases and expenses, so the numbers always agree.
 *
 * Past days have morning, evening and night shifts and are closed. Today
 * has the morning shift recorded and is left open for the live demo.
 *
 * Staff get last month's salaries on the first payday in the range, with
 * advances recovered; a few new advances stay open for this month's sheet.
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

    /** @var list<array{name: string, cnic: string, designation: string, salary: int, phone: string, bank?: bool}> Demo CNICs are made up. */
    private const STAFF = [
        ['name' => 'Muhammad Aslam', 'cnic' => '31202-4581236-7', 'designation' => 'Manager', 'salary' => 55000, 'phone' => '0300-6812345'],
        ['name' => 'Imran Haider', 'cnic' => '31202-6724519-3', 'designation' => 'Cashier', 'salary' => 38000, 'phone' => '0301-7723456'],
        ['name' => 'Bilal Ahmed', 'cnic' => '31201-9013847-5', 'designation' => 'Pump attendant', 'salary' => 28000, 'phone' => '0302-8834567'],
        ['name' => 'Zeeshan Ali', 'cnic' => '31202-3368021-9', 'designation' => 'Pump attendant', 'salary' => 28000, 'phone' => '0303-9945678'],
        ['name' => 'Ghulam Rasool', 'cnic' => '31203-7754102-1', 'designation' => 'Security guard', 'salary' => 30000, 'phone' => '0304-1056789', 'bank' => true],
        ['name' => 'Waqas Khan', 'cnic' => '31202-1196453-3', 'designation' => 'Helper', 'salary' => 22000, 'phone' => '0305-2167890'],
    ];

    /** @var array<int, array{staff: string, amount: int, note: string}> Advances by day index. */
    private const ADVANCES = [
        0 => ['staff' => 'Bilal Ahmed', 'amount' => 5000, 'note' => 'Family need'],
        5 => ['staff' => 'Zeeshan Ali', 'amount' => 3000, 'note' => 'Medical'],
        7 => ['staff' => 'Muhammad Aslam', 'amount' => 10000, 'note' => 'School fees'],
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
        private PayrollService $payroll,
        private TankService $tanks,
        private SupplierService $suppliers,
        private CreditService $credits,
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

        // Salaries are paid early in the month; the first such day in range, else the first day.
        $payday = $days->first(fn (Carbon $day) => $day->day <= 7 && ! $day->isToday()) ?? $days->first();
        $employees = $this->hireStaff($payday->copy()->subMonthNoOverflow()->startOfMonth());

        DB::transaction(function () use ($days, $nozzles, $products, $plan, $staff, $owner, $payday, $employees, &$stock) {
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
                $this->recordCredit($day, $index, $products, $stock, $staff, $owner, $isToday);
                $this->recordTankWork($day, $index, $staff, $owner);
                $this->paySuppliers($day, $index, $owner);

                if (isset(self::ADVANCES[$index])) {
                    $advance = self::ADVANCES[$index];
                    $this->payroll->giveAdvance($employees[$advance['staff']], $advance['amount'], PaymentMethod::Cash, $owner, $advance['note'], $day);
                }

                if ($day->equalTo($payday)) {
                    $this->paySalaries($payday, $employees, $owner);
                }

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
        // Fuel: each tank is refilled when it drops below 40% of capacity,
        // with a tanker load that fits the room left.
        foreach ($this->tanks->levels($day) as $row) {
            $product = $row['tank']->fuelProduct;
            $rule = self::RESTOCK[$product->code] ?? null;

            if ($rule === null || $row['percent'] >= 40) {
                continue;
            }

            $quantity = min($rule['quantity'], floor($row['ullage'] / 1000) * 1000);

            if ($quantity < 1000) {
                continue;
            }

            $product = $product->fresh();
            $rate = (float) $product->selling_price - $rule['margin'];

            $this->stock->recordPurchase(
                product: $product,
                date: $day,
                quantity: $quantity,
                rate: $rate,
                invoiceNumber: 'INV-'.$day->format('md').'-'.$row['tank']->id,
                recordedBy: $staff,
                tank: $row['tank'],
                supplier: Supplier::firstWhere('name', 'Bahawalpur Oil Depot'),
                paidNow: $this->roundTo($quantity * $rate * 0.7, 1000),
                paymentMethod: PaymentMethod::Bank,
            );

            $stock[$product->code] += $quantity;
        }

        // Counter items are ordered at the product level.
        foreach (self::RESTOCK as $code => $rule) {
            $product = $products[$code];

            if ($product->isMetered() || $stock[$code] >= $rule['reorder_below']) {
                continue;
            }

            $product = $product->fresh();

            $this->stock->recordPurchase(
                product: $product,
                date: $day,
                quantity: $rule['quantity'],
                rate: (float) $product->selling_price - $rule['margin'],
                invoiceNumber: 'INV-'.$day->format('md').'-'.strtoupper(substr($code, 0, 2)),
                recordedBy: $staff,
                supplier: Supplier::firstWhere('name', 'City Lubricants Traders'),
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
            $expenses[] = [ExpenseCategory::Internet, 'PTCL internet and phone', 2500, 3500, PaymentMethod::Bank];
            $expenses[] = [ExpenseCategory::Water, 'Water tanker for washroom block', 1200, 1800, PaymentMethod::Cash];
        }

        if ($day->equalTo(today()->subDays(self::DAYS - 3))) {
            $expenses[] = [ExpenseCategory::Calibration, 'OGRA dispenser calibration', 7000, 9000, PaymentMethod::Bank];
            $expenses[] = [ExpenseCategory::Licenses, 'Explosives licence renewal fee', 4000, 6000, PaymentMethod::Bank];
        }

        if ($day->dayOfWeek === Carbon::THURSDAY) {
            $expenses[] = [ExpenseCategory::Repairs, 'Canopy light replacement', 2500, 4500, PaymentMethod::Cash];
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

    /**
     * The newest hire joined on the 10th of the payroll month, so their first
     * salary is worked out for the days they were on staff.
     *
     * @return Collection<string, Employee> keyed by name
     */
    private function hireStaff(Carbon $payrollMonth): Collection
    {
        return collect(self::STAFF)->mapWithKeys(function (array $person, int $i) use ($payrollMonth) {
            $isNewest = $i === count(self::STAFF) - 1;

            $employee = Employee::create([
                'name' => $person['name'],
                'designation' => $person['designation'],
                'phone' => $person['phone'],
                'cnic_number' => $person['cnic'],
                'monthly_salary' => $person['salary'],
                'joined_on' => $isNewest ? $payrollMonth->copy()->addDays(9) : today()->subYears(5 - $i)->startOfMonth(),
                'notes' => ($person['bank'] ?? false) ? 'Salary by bank transfer' : null,
                'is_active' => true,
            ]);

            return [$person['name'] => $employee];
        });
    }

    /**
     * Prepares and pays last month's salaries on payday, with one absence and
     * one bonus so the slips show every column.
     *
     * @param  Collection<string, Employee>  $employees
     */
    private function paySalaries(Carbon $payday, Collection $employees, ?User $owner): void
    {
        $month = $payday->copy()->subMonthNoOverflow()->startOfMonth();

        $this->payroll->prepare($month)->each(function (SalarySlip $slip) use ($employees, $owner, $payday) {
            $name = $slip->employee->name;

            if ($name === 'Zeeshan Ali') {
                $this->payroll->update($slip, 2, 0, 0, (float) $slip->advance_deduction, '2 days leave without pay');
            }

            if ($name === 'Imran Haider') {
                $this->payroll->update($slip, 0, 3000, 0, (float) $slip->advance_deduction, 'Overtime during stock audit');
            }

            $method = $employees[$name]->notes ? PaymentMethod::Bank : PaymentMethod::Cash;
            $this->payroll->pay($slip->fresh(), $method, $owner, $payday);
        });
    }

    private function closeDay(Carbon $day, ?User $staff): void
    {
        $summary = $this->closings->summary($day);
        $totalSales = $summary['total_sales'];
        $openingCash = Station::defaultOpeningCash();

        $cardSales = $this->roundTo($totalSales * $this->random->getInt(12, 20) / 100, 100);

        $expectedCash = $openingCash
            + ($totalSales - $cardSales - $summary['credit_sales'])
            + $summary['customer_receipts']
            - $summary['cash_expenses']
            - $summary['supplier_payments'];

        // Mostly balanced, with the occasional small shortage or excess.
        $difference = [0, 0, 0, 0, 0, -500, -2000, 300][$this->random->getInt(0, 7)];

        $this->closings->close(
            date: $day,
            openingCash: $openingCash,
            actualCash: round($expectedCash + $difference, 2),
            cardSales: $cardSales,
            closedBy: $staff,
            notes: $difference < 0 ? 'Cash short, noted for follow-up.' : null,
        );
    }

    /**
     * Fuel on credit for fleet customers, a counter item on credit, and
     * payments received every few days.
     *
     * @param  Collection<string, FuelProduct>  $products
     * @param  array<string, float>  $stock
     */
    private function recordCredit(Carbon $day, int $index, Collection $products, array &$stock, ?User $staff, ?User $owner, bool $isToday): void
    {
        $customers = Customer::query()->orderBy('id')->get()->keyBy('name');

        $plan = [
            ['Khan Goods Transport', 'diesel', 120, 260],
            ['Al-Rehman Flour Mills', 'diesel', 60, 140],
            ['City School Vans', 'petrol', 30, 70],
            ['Haji Akram Farms', 'diesel', 40, 90],
            ['District Ambulance Service', 'petrol', 25, 60],
        ];

        // Today stays light so the live demo entry stands out.
        $todays = $isToday ? [$plan[0]] : array_filter($plan, fn (array $row, int $i) => ($index + $i) % 2 === 0, ARRAY_FILTER_USE_BOTH);

        foreach ($todays as [$name, $code, $min, $max]) {
            $this->attempt(fn () => $this->credits->recordCredit(
                customer: $customers[$name],
                product: $products[$code]->fresh(),
                quantity: $this->random->getInt($min, $max),
                amount: null,
                slipNumber: 'CR-'.$day->format('md').'-'.$customers[$name]->id,
                recordedBy: $staff,
                date: $day,
            ));
        }

        if ($index === 3 && $stock['engine-oil-4l'] >= 1) {
            $this->attempt(function () use ($products, $day, $staff, $customers, &$stock) {
                $this->sales->recordCounterSale($products['engine-oil-4l']->fresh(), $day, 1, $staff, customer: $customers['Khan Goods Transport']);
                $stock['engine-oil-4l'] -= 1;
            });
        }

        // Customers settle part of their account every third day.
        if ($index === 4 || $isToday) {
            foreach ($customers as $customer) {
                $balance = $this->credits->balance($customer);

                if ($balance < 5000 || ($isToday && $customer->name !== 'Al-Rehman Flour Mills')) {
                    continue;
                }

                $this->attempt(fn () => $this->credits->recordPayment(
                    customer: $customer,
                    amount: $this->roundTo($balance * ($customer->credit_limit === null ? 0.9 : 0.35), 500),
                    method: $customer->credit_limit === null ? PaymentMethod::Cheque : PaymentMethod::Cash,
                    recordedBy: $owner,
                    reference: $customer->credit_limit === null ? 'Chq '.(410000 + $index) : null,
                    date: $day,
                ));
            }
        }
    }

    /**
     * Morning dips every few days with small natural variances, and one
     * petrol transfer between tanks.
     */
    private function recordTankWork(Carbon $day, int $index, ?User $staff, ?User $owner): void
    {
        if ($index % 3 === 1) {
            foreach ($this->tanks->levels($day) as $row) {
                $variance = $row['level'] * $this->random->getInt(-30, 8) / 10000;

                $this->tanks->recordDip(
                    tank: $row['tank'],
                    measured: round(max(0, $row['level'] + $variance), 0),
                    recordedBy: $staff,
                    notes: $variance < 0 ? 'Evaporation / handling loss' : null,
                    date: $day,
                );
            }
        }

        if ($index === 6) {
            $from = Tank::firstWhere('name', 'Tank 2 · Petrol');
            $to = Tank::firstWhere('name', 'Tank 1 · Petrol');
            $this->attempt(fn () => $this->tanks->transfer($from, $to, 800, $owner, 'Balancing levels before decanting', $day));
        }
    }

    /**
     * Pays suppliers part of what is due on a few days, leaving some bills
     * open and one overdue for the payment schedule.
     */
    private function paySuppliers(Carbon $day, int $index, ?User $owner): void
    {
        $payments = [
            2 => ['Indus Fuel Logistics', 20000, PaymentMethod::Cash],
            4 => ['Bahawalpur Oil Depot', 2500000, PaymentMethod::Bank],
            8 => ['Bahawalpur Oil Depot', 2000000, PaymentMethod::Bank],
            7 => ['City Lubricants Traders', 18500, PaymentMethod::Cheque],
        ];

        if (! isset($payments[$index])) {
            return;
        }

        [$name, $amount, $method] = $payments[$index];
        $supplier = Supplier::firstWhere('name', $name);

        $this->attempt(fn () => $this->suppliers->recordPayment(
            supplier: $supplier,
            amount: min($amount, $this->suppliers->balance($supplier)),
            method: $method,
            recordedBy: $owner,
            reference: $method === PaymentMethod::Cash ? null : 'TXN-'.$day->format('md'),
            date: $day,
        ));
    }

    /**
     * Demo entries that would break a business rule (e.g. a credit limit) are skipped.
     */
    private function attempt(callable $callback): void
    {
        try {
            DB::transaction($callback);
        } catch (ValidationException) {
            // Skip this entry; the rest of the demo stays consistent.
        }
    }

    private function roundTo(float|int $value, int $step): float
    {
        return (float) (round($value / $step) * $step);
    }
}
