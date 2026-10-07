<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for the sidebar. Items whose route does not
 * exist render as disabled entries, so modules can be added one by one.
 */
final class Navigation
{
    /**
     * @return list<array{heading: ?string, items: list<array{label: string, icon: string, route: string, active: string, enabled: bool}>}>
     */
    public static function for(?User $user): array
    {
        $groups = [
            ['heading' => null, 'items' => [
                self::item('Dashboard', 'speedometer2', 'dashboard'),
            ]],
            ['heading' => 'Daily operations', 'items' => [
                self::item('Meter readings', 'fuel-pump', 'meter-readings.index'),
                self::item('Nozzles live', 'broadcast-pin', 'nozzles.index'),
                self::item('Sales', 'receipt', 'sales.index'),
                self::item('Debt register', 'journal-bookmark', 'credits.index'),
                self::item('Misc. register', 'wallet2', 'expenses.index'),
                self::item('Daily closing', 'journal-check', 'closings.index'),
            ]],
            ['heading' => 'Tanks & stock', 'items' => [
                self::item('Tanks', 'database-fill', 'tanks.index'),
                self::item('Inventory movements', 'arrow-left-right', 'stock.movements', active: 'stock.movements'),
                self::item('Stock', 'bar-chart-steps', 'stock.index', active: 'stock.index'),
                self::item('Purchases', 'truck', 'purchases.index'),
                self::item('Products & prices', 'droplet-half', 'fuel-products.index'),
                self::item('Pumps & nozzles', 'diagram-3', 'pumps.index'),
            ]],
            ['heading' => 'Accounts', 'items' => [
                self::item('Customers', 'person-vcard', 'customers.index'),
                self::item('Suppliers', 'building', 'suppliers.index', roles: [UserRole::Owner]),
            ]],
            ['heading' => 'Staff', 'items' => [
                self::item('Employees', 'people', 'employees.index', roles: [UserRole::Owner]),
                self::item('Payroll', 'cash-coin', 'payroll.index', roles: [UserRole::Owner], active: ['payroll.index', 'payroll.slips.*']),
            ]],
            ['heading' => 'Reports', 'items' => [
                self::item('Daily report', 'file-earmark-text', 'reports.daily', active: 'reports.daily'),
                self::item('Nozzle performance', 'bar-chart-line', 'reports.nozzles', active: 'reports.nozzles'),
                self::item('Payroll report', 'file-earmark-person', 'payroll.report', roles: [UserRole::Owner], active: 'payroll.report'),
                self::item('Profit & loss', 'graph-up-arrow', 'reports.profit-loss', roles: [UserRole::Owner], active: 'reports.profit-loss'),
                self::item('Audit trail', 'shield-lock', 'activity.index', roles: [UserRole::Owner]),
                self::item('Settings', 'gear', 'settings.edit', roles: [UserRole::Owner]),
            ]],
        ];

        return collect($groups)
            ->map(function (array $group) use ($user) {
                $group['items'] = collect($group['items'])
                    ->filter(fn (array $item) => $item['roles'] === [] || ($user?->hasRole(...$item['roles']) ?? false))
                    ->map(fn (array $item) => collect($item)->except('roles')->all())
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * The menu entry for the current page, used for the icon in the top bar.
     *
     * @return array{label: string, icon: string, route: string, active: string|list<string>, enabled: bool}|null
     */
    public static function current(?User $user): ?array
    {
        foreach (self::for($user) as $group) {
            foreach ($group['items'] as $item) {
                if (request()->routeIs(...(array) $item['active'])) {
                    return $item;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<UserRole>  $roles
     * @return array{label: string, icon: string, route: string, active: string, enabled: bool, roles: list<UserRole>}
     */
    private static function item(string $label, string $icon, string $route, array $roles = [], string|array|null $active = null): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            // "sales.index" highlights for every "sales.*" page.
            'active' => $active ?? (str_contains($route, '.') ? strtok($route, '.').'.*' : $route),
            'enabled' => Route::has($route),
            'roles' => $roles,
        ];
    }
}
