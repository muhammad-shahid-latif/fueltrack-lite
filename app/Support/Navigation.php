<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for the sidebar. Items whose route does not
 * exist yet render as disabled "Soon" entries, so modules can be added
 * one by one without touching the layout.
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
            ['heading' => 'Operations', 'items' => [
                self::item('Meter Readings', 'fuel-pump', 'meter-readings.index'),
                self::item('Sales', 'receipt', 'sales.index'),
                self::item('Expenses', 'wallet2', 'expenses.index'),
                self::item('Daily Closing', 'journal-check', 'closings.index'),
            ]],
            ['heading' => 'Fuel', 'items' => [
                self::item('Fuel Products', 'droplet-half', 'fuel-products.index'),
                self::item('Pumps & Nozzles', 'diagram-3', 'pumps.index'),
                self::item('Stock', 'bar-chart-steps', 'stock.index'),
                self::item('Purchases', 'truck', 'purchases.index'),
            ]],
            ['heading' => 'Reports', 'items' => [
                self::item('Daily Report', 'file-earmark-text', 'reports.daily'),
            ]],
            ['heading' => null, 'items' => [
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
     * @param  list<UserRole>  $roles
     * @return array{label: string, icon: string, route: string, active: string, enabled: bool, roles: list<UserRole>}
     */
    private static function item(string $label, string $icon, string $route, array $roles = []): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            // "sales.index" highlights for every "sales.*" page.
            'active' => str_contains($route, '.') ? strtok($route, '.').'.*' : $route,
            'enabled' => Route::has($route),
            'roles' => $roles,
        ];
    }
}
