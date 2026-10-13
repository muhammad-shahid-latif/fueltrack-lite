<?php

use App\Models\Nozzle;
use App\Models\Pump;
use App\Models\User;
use App\Services\NozzleReportService;
use App\Services\PayrollReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed());

test('the live board shows each nozzle with today\'s readings', function () {
    $board = app(NozzleReportService::class)->live();
    $demo = $board->first(fn (array $row) => $row['nozzle']->pump->name === 'Pump 01' && $row['nozzle']->number === 1);

    expect($board)->toHaveCount(Nozzle::count())
        ->and($demo['shifts'])->toBe(['morning'])
        ->and($demo['liters'])->toBeGreaterThan(0)
        ->and(round($board->sum('share')))->toBe(100.0);
});

test('nozzle performance totals agree with the daily matrix', function () {
    $report = app(NozzleReportService::class)->performance(today()->subDays(6), today());

    expect($report['days'])->toHaveCount(7)
        ->and(round(array_sum($report['daily_totals']), 2))->toBe($report['totals']['liters'])
        ->and($report['rows']->first()['liters'])->toBeGreaterThanOrEqual($report['rows']->last()['liters']);
});

test('the payroll report covers slips, advances and the audit trail', function () {
    $report = app(PayrollReportService::class)->report(today()->subMonths(2), today());

    expect($report['slips'])->not->toBeEmpty()
        ->and($report['totals']['net'])->toBe(round((float) $report['slips']->sum('net_salary'), 2))
        ->and($report['advances'])->not->toBeEmpty()
        ->and($report['audit'])->not->toBeEmpty();

    $this->actingAs(User::firstWhere('email', 'owner@alnoor.com'))
        ->get(route('payroll.report'))
        ->assertOk()
        ->assertSee('Salary transactions')
        ->assertSee('Payroll audit trail');
});

test('machines are listed in natural order, then by nozzle number', function () {
    Pump::query()->where('name', 'Pump 01')->update(['name' => 'Machine 10']);
    Pump::query()->where('name', 'Pump 02')->update(['name' => ' Machine 04']);
    Pump::query()->where('name', 'Pump 03')->update(['name' => 'Machine 2']);

    $labels = app(NozzleReportService::class)->live()->map(fn (array $row) => trim($row['nozzle']->pump->name).' N'.$row['nozzle']->number)->all();

    expect($labels)->toBe(['Machine 2 N1', 'Machine 2 N2', 'Machine 04 N1', 'Machine 04 N2', 'Machine 10 N1', 'Machine 10 N2']);
});
