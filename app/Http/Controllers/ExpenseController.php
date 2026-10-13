<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Services\DailyClosingService;
use App\Services\ExpenseService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Office Expense register: utilities, maintenance and operational costs,
 * plus the payroll entries written by payroll.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'group' => ['nullable', Rule::in(ExpenseCategory::groups())],
            'category' => ['nullable', Rule::enum(ExpenseCategory::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $from = $request->date('from') ?? BusinessClock::today();
        $to = $request->date('to') ?? BusinessClock::today();

        $period = fn (Builder $query) => $query
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);

        $scope = fn (Builder $query) => $query
            ->tap($period)
            ->when($request->input('group'), fn (Builder $q, string $group) => $q->whereIn('category', ExpenseCategory::valuesIn($group)))
            ->when($request->input('category'), fn (Builder $q, string $category) => $q->where('category', $category))
            ->when($request->input('q'), fn (Builder $q, string $term) => $q->where(fn (Builder $w) => $w->where('description', 'like', "%{$term}%")->orWhere('notes', 'like', "%{$term}%")));

        $byCategory = Expense::query()
            ->tap($period)
            ->groupBy('category')
            ->select('category')
            ->selectRaw('SUM(amount) as total, COUNT(*) as entries')
            ->orderByDesc('total')
            ->toBase()
            ->get()
            ->map(fn (object $row) => (object) [...(array) $row, 'category' => ExpenseCategory::from($row->category)]);

        return view('expenses.index', [
            'expenses' => Expense::query()
                ->tap($scope)
                ->with(['recorder', 'salarySlip', 'salaryAdvance'])
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'byCategory' => $byCategory,
            'byGroup' => collect(ExpenseCategory::groups())->mapWithKeys(fn (string $group) => [
                $group => round((float) $byCategory->filter(fn (object $row) => $row->category->group() === $group)->sum('total'), 2),
            ]),
            'filteredTotal' => round((float) Expense::query()->tap($scope)->sum('amount'), 2),
            'from' => $from,
            'to' => $to,
            'filters' => $request->only(['group', 'category', 'q']),
        ]);
    }

    public function create(): View
    {
        return view('expenses.create', [
            'expense' => new Expense(['payment_method' => PaymentMethod::Cash]),
            'groups' => ExpenseCategory::grouped(),
            'paymentMethods' => PaymentMethod::cases(),
            'businessDate' => BusinessClock::today(),
        ]);
    }

    public function store(StoreExpenseRequest $request, ExpenseService $expenses): RedirectResponse
    {
        $expense = $expenses->record(
            date: BusinessClock::today(),
            category: $request->enum('category', ExpenseCategory::class),
            description: $request->string('description')->trim()->toString(),
            amount: (float) $request->input('amount'),
            paymentMethod: $request->enum('payment_method', PaymentMethod::class),
            recordedBy: $request->user(),
            notes: $request->input('notes'),
        );

        return redirect()
            ->route('expenses.index', ['from' => $expense->business_date->toDateString(), 'to' => $expense->business_date->toDateString()])
            ->with('success', "Expense saved: {$expense->description} · ".Format::money($expense->amount));
    }

    public function edit(Expense $expense, DailyClosingService $closings): View|RedirectResponse
    {
        if ($expense->isPayroll()) {
            return redirect()->route('payroll.index')->with('warning', 'Salaries and advances are changed from Payroll.');
        }

        if ($closings->isClosed($expense->business_date)) {
            return redirect()
                ->route('expenses.index', ['from' => $expense->business_date->toDateString(), 'to' => $expense->business_date->toDateString()])
                ->with('warning', sprintf('%s is closed. Reopen it from Daily closing to make changes.', $expense->business_date->format('d M Y')));
        }

        return view('expenses.create', [
            'expense' => $expense,
            'groups' => ExpenseCategory::grouped(),
            'paymentMethods' => PaymentMethod::cases(),
            'businessDate' => $expense->business_date,
        ]);
    }

    public function update(StoreExpenseRequest $request, Expense $expense, ExpenseService $expenses): RedirectResponse
    {
        $expense = $expenses->update(
            expense: $expense,
            category: $request->enum('category', ExpenseCategory::class),
            description: $request->string('description')->trim()->toString(),
            amount: (float) $request->input('amount'),
            paymentMethod: $request->enum('payment_method', PaymentMethod::class),
            notes: $request->input('notes'),
        );

        return redirect()
            ->route('expenses.index', ['from' => $expense->business_date->toDateString(), 'to' => $expense->business_date->toDateString()])
            ->with('success', "Expense updated: {$expense->description} · ".Format::money($expense->amount));
    }

    public function destroy(Expense $expense, ExpenseService $expenses): RedirectResponse
    {
        $expenses->delete($expense);

        return redirect()
            ->route('expenses.index', ['from' => $expense->business_date->toDateString(), 'to' => $expense->business_date->toDateString()])
            ->with('success', "Expense deleted: {$expense->description} · ".Format::money($expense->amount));
    }
}
