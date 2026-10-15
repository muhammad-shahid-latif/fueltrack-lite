<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Single source of truth for the sidebar and the section tabs.
 *
 * Related pages are grouped into one sidebar entry (a "section"); the layout
 * shows the section's pages as tabs at the top of each of them. Items whose
 * route does not exist render as disabled entries.
 */
final class Navigation
{
    /**
     * Sidebar groups, filtered for the user.
     *
     * @return list<array{heading: ?string, items: list<array<string, mixed>>}>
     */
    public static function for(?User $user): array
    {
        $groups = [
            ['heading' => null, 'items' => [
                self::page('Dashboard', 'speedometer2', 'dashboard'),
            ]],
            ['heading' => 'Daily work', 'items' => [
                self::section('Meter readings', 'fuel-pump', [
                    self::page('Readings', 'fuel-pump', 'meter-readings.index'),
                    self::page('Nozzle meters', 'speedometer2', 'nozzles.index', active: 'nozzles.index'),
                ]),
                self::page('Sales', 'receipt', 'sales.index'),
                self::section('Khata register', 'journal-bookmark', [
                    self::page('Entries', 'journal-text', 'credits.index'),
                    self::page('Customers', 'person-vcard', 'customers.index'),
                ]),
                self::page('Office Expense', 'wallet2', 'expenses.index'),
                self::page('Daily closing', 'journal-check', 'closings.index'),
            ]],
            ['heading' => 'Fuel & stock', 'items' => [
                self::page('Tanks', 'database-fill', 'tanks.index'),
                self::section('Stock & deliveries', 'box-seam', [
                    self::page('Stock', 'bar-chart-steps', 'stock.index', active: 'stock.index'),
                    self::page('Movements', 'arrow-left-right', 'stock.movements', active: 'stock.movements'),
                    self::page('Deliveries', 'truck', 'purchases.index'),
                    // Suppliers belong with the deliveries they bring and the bills they send.
                    self::page('Suppliers', 'building', 'suppliers.index', roles: [UserRole::Owner]),
                ]),
            ]],
            ['heading' => 'Staff', 'items' => [
                self::section('Staff & payroll', 'people', [
                    self::page('Employees', 'people', 'employees.index', roles: [UserRole::Owner]),
                    self::page('Payroll', 'cash-coin', 'payroll.index', roles: [UserRole::Owner], active: ['payroll.index', 'payroll.slips.*']),
                    self::page('Payroll report', 'file-earmark-person', 'payroll.report', roles: [UserRole::Owner], active: 'payroll.report'),
                ]),
            ]],
            ['heading' => 'Reports', 'items' => [
                self::section('Reports', 'file-earmark-bar-graph', [
                    self::page('Daily report', 'file-earmark-text', 'reports.daily', active: 'reports.daily'),
                    self::page('Nozzle performance', 'bar-chart-line', 'reports.nozzles', active: 'reports.nozzles'),
                    self::page('Profit & loss', 'graph-up-arrow', 'reports.profit-loss', roles: [UserRole::Owner], active: 'reports.profit-loss'),
                ]),
                self::page('Audit trail', 'shield-lock', 'activity.index', roles: [UserRole::Owner]),
            ]],
            ['heading' => 'Setup', 'items' => [
                self::section('Setup', 'sliders', [
                    self::page('Products & prices', 'droplet-half', 'fuel-products.index'),
                    self::page('Pumps & nozzles', 'diagram-3', 'pumps.index'),
                    self::page('Shifts', 'clock-history', 'shifts.edit', roles: [UserRole::Owner]),
                    self::page('Manager access', 'person-lock', 'access.edit', roles: [UserRole::Owner]),
                    self::page('WhatsApp', 'whatsapp', 'whatsapp.edit', roles: [UserRole::Owner]),
                    self::page('Settings', 'gear', 'settings.edit', roles: [UserRole::Owner]),
                ]),
            ]],
        ];

        return collect($groups)
            ->map(function (array $group) use ($user) {
                $group['items'] = collect($group['items'])
                    ->map(fn (array $item) => self::resolve($item, $user))
                    ->filter()
                    ->values()
                    ->all();

                return $group;
            })
            ->filter(fn (array $group) => $group['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * The sidebar entry for the current page, used for the icon in the top bar.
     *
     * @return array<string, mixed>|null
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
     * Tabs of the section the current page belongs to, or [] when the page
     * is not part of a section with more than one visible page.
     *
     * @return list<array{label: string, icon: string, route: string, current: bool}>
     */
    public static function tabs(?User $user): array
    {
        $item = self::current($user);

        if (! $item || count($item['tabs']) < 2) {
            return [];
        }

        return array_map(fn (array $tab) => [
            'label' => $tab['label'],
            'icon' => $tab['icon'],
            'route' => $tab['route'],
            'current' => request()->routeIs(...(array) $tab['active']),
        ], $item['tabs']);
    }

    /**
     * Applies roles to a page or section; a section takes its link and
     * highlight from the pages the user can open.
     *
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function resolve(array $item, ?User $user): ?array
    {
        // Role, and for the manager the modules the owner left switched on.
        $allowed = fn (array $entry) => ($entry['roles'] === [] || ($user?->hasRole(...$entry['roles']) ?? false))
            && StaffAccess::allowsRoute($user, $entry['route']);

        if ($item['tabs'] === []) {
            return $allowed($item) ? collect($item)->except('roles')->all() : null;
        }

        $tabs = array_values(array_filter($item['tabs'], $allowed));

        if ($tabs === []) {
            return null;
        }

        return [
            'label' => $item['label'],
            'icon' => $item['icon'],
            'route' => $tabs[0]['route'],
            'active' => collect($tabs)->flatMap(fn (array $tab) => (array) $tab['active'])->unique()->values()->all(),
            'enabled' => $tabs[0]['enabled'],
            'tabs' => array_map(fn (array $tab) => collect($tab)->except('roles')->all(), $tabs),
        ];
    }

    /**
     * One sidebar entry made of several pages, shown as tabs.
     *
     * @param  list<array<string, mixed>>  $pages
     * @return array<string, mixed>
     */
    private static function section(string $label, string $icon, array $pages): array
    {
        return ['label' => $label, 'icon' => $icon, 'tabs' => $pages, 'roles' => []];
    }

    /**
     * @param  list<UserRole>  $roles
     * @return array<string, mixed>
     */
    private static function page(string $label, string $icon, string $route, array $roles = [], string|array|null $active = null): array
    {
        return [
            'label' => $label,
            'icon' => $icon,
            'route' => $route,
            // "sales.index" highlights for every "sales.*" page.
            'active' => $active ?? (str_contains($route, '.') ? strtok($route, '.').'.*' : $route),
            'enabled' => Route::has($route),
            'roles' => $roles,
            'tabs' => [],
        ];
    }
}
