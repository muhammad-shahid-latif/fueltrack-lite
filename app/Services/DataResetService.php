<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\FuelProduct;
use App\Models\Tank;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;

/**
 * Wipes the station's data to start fresh, for example after the demo.
 *
 * Always kept: user logins and settings (station details, shifts, manager
 * access, WhatsApp, relief rate). Always erased: every daily record and the
 * people registers (customers, suppliers, staff and their documents).
 *
 * With $keepSetup the fuel products and prices, tanks, pumps and nozzles
 * stay; current stock becomes each tank's and product's opening stock and
 * nozzle meters keep their position, so nothing jumps after the reset.
 */
class DataResetService
{
    /** Daily records, children before parents so foreign keys hold. */
    private const RECORDS = [
        'whatsapp_logs',
        'relief_sales',
        'owed_fuel_deliveries',
        'customer_payments',
        'customer_credits',
        'supplier_payments',
        'salary_advances',
        'salary_slips',
        'expenses',
        'stock_adjustments',
        'fuel_purchases',
        'sales',
        'meter_readings',
        'daily_closings',
        'activity_logs',
    ];

    /** People registers, erased after the records that point at them. */
    private const PEOPLE = ['customers', 'suppliers', 'employees'];

    /** Station setup, erased only on a full reset. */
    private const SETUP = ['nozzles', 'pumps', 'tanks', 'fuel_prices', 'fuel_products'];

    public function __construct(
        private TankService $tanks,
        private FuelStockService $stock,
    ) {}

    /**
     * @return array<string, int> rows removed per table
     */
    public function reset(User $by, bool $keepSetup): array
    {
        BusinessClock::assertTrustworthy();

        $removed = DB::transaction(function () use ($keepSetup) {
            if ($keepSetup) {
                $this->carryStockForward();
            }

            $tables = [...self::RECORDS, ...self::PEOPLE, ...($keepSetup ? [] : self::SETUP)];
            $removed = [];

            foreach ($tables as $table) {
                $removed[$table] = DB::table($table)->delete();
            }

            return $removed;
        });

        // Scans of staff CNICs and photos go with the staff register.
        Storage::disk('local')->deleteDirectory('employees');
        Once::flush();

        // The first line of the new audit trail says who reset it and when.
        ActivityLog::create([
            'user_id' => $by->id,
            'event' => 'reset',
            'subject_type' => User::class,
            'subject_id' => $by->id,
            'description' => $keepSetup
                ? 'All records erased. Fuel products, tanks, pumps and nozzles kept.'
                : 'All data erased for a fresh start.',
            'changes' => array_filter($removed),
            'ip_address' => request()->ip(),
        ]);

        return $removed;
    }

    /**
     * Today's book stock and cost become the opening figures, so tanks and
     * products show the same levels once their movements are gone.
     */
    private function carryStockForward(): void
    {
        $today = BusinessClock::today();

        foreach (Tank::query()->get() as $tank) {
            $tank->forceFill(['opening_stock' => max(0, $this->tanks->level($tank, $today))])->saveQuietly();
        }

        foreach (FuelProduct::query()->get() as $product) {
            $product->forceFill([
                'opening_stock' => max(0, $this->stock->currentStock($product, $today)),
                'cost_price' => $product->currentAverageCost() ?? $product->cost_price,
            ])->saveQuietly();
        }
    }
}
