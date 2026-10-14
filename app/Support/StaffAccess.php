<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Once;
use Illuminate\Support\Str;

/**
 * What the manager (staff) account may open, set by the owner in
 * Setup → Manager access. The owner is never restricted.
 *
 * Only switched-off modules are stored, so a module added later is open
 * until the owner decides otherwise.
 */
final class StaffAccess
{
    public const SETTING_KEY = 'staff_blocked_modules';

    /**
     * key => label, icon, group, what the manager can do there, and the route
     * names it covers. Dashboard, logout and owner-only pages are not listed.
     *
     * @var array<string, array{label: string, icon: string, group: string, can: string, routes: list<string>}>
     */
    public const MODULES = [
        'meter_readings' => ['label' => 'Meter readings', 'icon' => 'fuel-pump', 'group' => 'Daily work',
            'can' => 'Enter shift meter readings and see the nozzle meters.', 'routes' => ['meter-readings.*', 'nozzles.*']],
        'sales' => ['label' => 'Sales', 'icon' => 'receipt', 'group' => 'Daily work',
            'can' => 'See all sales and sell counter items (oil, coolant…).', 'routes' => ['sales.*']],
        'khata' => ['label' => 'Khata register', 'icon' => 'journal-bookmark', 'group' => 'Daily work',
            'can' => 'Give fuel on credit, receive payments, see customer balances and give owed fuel.', 'routes' => ['credits.*', 'customers.*']],
        'expenses' => ['label' => 'Office Expense', 'icon' => 'wallet2', 'group' => 'Daily work',
            'can' => 'Record and see office expenses.', 'routes' => ['expenses.*']],
        'closings' => ['label' => 'Daily closing', 'icon' => 'journal-check', 'group' => 'Daily work',
            'can' => 'Count the cash drawer, close the day and see past closings.', 'routes' => ['closings.*']],
        'tanks' => ['label' => 'Tanks', 'icon' => 'database-fill', 'group' => 'Fuel & stock',
            'can' => 'See tank levels and take dip readings.', 'routes' => ['tanks.*']],
        'stock' => ['label' => 'Stock & deliveries', 'icon' => 'box-seam', 'group' => 'Fuel & stock',
            'can' => 'See stock and movements, and receive fuel deliveries.', 'routes' => ['stock.*', 'purchases.*']],
        'reports' => ['label' => 'Reports', 'icon' => 'file-earmark-bar-graph', 'group' => 'Reports & setup',
            'can' => 'Open the daily report and nozzle performance.', 'routes' => ['reports.daily', 'reports.nozzles']],
        'setup' => ['label' => 'Products & pumps (view)', 'icon' => 'sliders', 'group' => 'Reports & setup',
            'can' => 'See products, prices, pumps and nozzles. Changes stay owner-only.', 'routes' => ['fuel-products.*', 'pumps.*']],
        'dashboard_money' => ['label' => 'Money on the dashboard', 'icon' => 'cash-stack', 'group' => 'Reports & setup',
            'can' => 'See today\'s sales, expenses, cash in drawer, receivables and the sales chart on the dashboard.', 'routes' => []],
    ];

    /**
     * @return list<string>
     */
    public static function blocked(): array
    {
        return once(function () {
            $saved = json_decode((string) Setting::get(self::SETTING_KEY), true);

            return is_array($saved) ? array_values(array_intersect($saved, array_keys(self::MODULES))) : [];
        });
    }

    /**
     * @param  list<string>  $blocked
     */
    public static function save(array $blocked): void
    {
        Setting::putMany([self::SETTING_KEY => json_encode(array_values(array_intersect($blocked, array_keys(self::MODULES))))]);
        Once::flush();
    }

    /** Whether the user may use a module. The owner always may. */
    public static function can(?User $user, string $module): bool
    {
        if ($user === null || $user->isOwner()) {
            return true;
        }

        return ! in_array($module, self::blocked(), true);
    }

    /** The module a route belongs to, or null for pages that are never restricted. */
    public static function moduleFor(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        foreach (self::MODULES as $key => $module) {
            foreach ($module['routes'] as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function allowsRoute(?User $user, ?string $routeName): bool
    {
        $module = self::moduleFor($routeName);

        return $module === null || self::can($user, $module);
    }

    /** Label of the module a route belongs to, for the "turned off" message. */
    public static function labelFor(?string $routeName): ?string
    {
        $module = self::moduleFor($routeName);

        return $module ? self::MODULES[$module]['label'] : null;
    }

    /**
     * Modules grouped for the settings page.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::MODULES as $key => $module) {
            $groups[$module['group']][$key] = $module;
        }

        return $groups;
    }

    /** Guard against routes renamed in code but not here. */
    public static function routeExists(string $pattern): bool
    {
        return collect(Route::getRoutes()->getRoutesByName())->keys()->contains(fn (string $name) => Str::is($pattern, $name));
    }
}
