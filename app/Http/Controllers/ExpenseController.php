<?php

namespace App\Http\Controllers;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreExpenseRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Support\BusinessClock;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->date('from') ?? BusinessClock::today();
        $to = $request->date('to') ?? BusinessClock::today();

        $scope = fn (Builder $query) => $query
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);

        return view('expenses.index', [
            'expenses' => Expense::query()
                ->tap($scope)
                ->with('recorder')
                ->orderByDesc('business_date')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString(),
            'byCategory' => Expense::query()
                ->tap($scope)
                ->groupBy('category')
                ->select('category')
                ->selectRaw('SUM(amount) as total')
                ->orderByDesc('total')
                ->toBase()
                ->get(),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function create(): View
    {
        return view('expenses.create', [
            'categories' => ExpenseCategory::cases(),
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
}
