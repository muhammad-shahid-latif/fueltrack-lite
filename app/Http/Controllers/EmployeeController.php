<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\SaveEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeDocuments;
use App\Services\PayrollService;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function index(PayrollService $payroll): View
    {
        $employees = Employee::query()
            ->with(['salarySlips' => fn ($query) => $query->whereDate('month', BusinessClock::today()->startOfMonth())])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $active = $employees->where('is_active', true);

        return view('employees.index', [
            'employees' => $employees,
            'advances' => $payroll->outstandingAdvances($employees->pluck('id')),
            'monthlyBill' => round($active->sum(fn (Employee $employee) => (float) $employee->monthly_salary), 2),
            'activeCount' => $active->count(),
        ]);
    }

    public function create(): View
    {
        return view('employees.form', ['employee' => new Employee(['joined_on' => BusinessClock::today(), 'is_active' => true])]);
    }

    public function store(SaveEmployeeRequest $request, EmployeeDocuments $documents): RedirectResponse
    {
        $employee = Employee::create([...$request->employeeData(), 'is_active' => true]);
        $documents->sync($employee, $request);

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', "{$employee->name} added to staff.");
    }

    public function show(Employee $employee, PayrollService $payroll): View
    {
        $employee->load([
            'advances' => fn ($query) => $query->with(['recorder', 'expense'])->latest('business_date')->latest('id'),
            'salarySlips' => fn ($query) => $query->latest('month'),
        ]);

        return view('employees.show', [
            'employee' => $employee,
            'outstanding' => $payroll->outstandingAdvance($employee),
            'paymentMethods' => [PaymentMethod::Cash, PaymentMethod::Bank],
            'businessDate' => BusinessClock::today(),
        ]);
    }

    public function edit(Employee $employee): View
    {
        return view('employees.form', ['employee' => $employee]);
    }

    public function update(SaveEmployeeRequest $request, Employee $employee, EmployeeDocuments $documents): RedirectResponse
    {
        $employee->update([...$request->employeeData(), 'is_active' => $request->boolean('is_active')]);
        $documents->sync($employee, $request);

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', "{$employee->name}'s details saved.");
    }

    public function destroy(Employee $employee, EmployeeDocuments $documents): RedirectResponse
    {
        if ($employee->advances()->exists() || $employee->salarySlips()->exists()) {
            return back()->with('danger', "{$employee->name} has salary or advance records, so they cannot be deleted. Mark them inactive instead.");
        }

        $documents->deleteAll($employee);
        $employee->delete();

        return redirect()->route('employees.index')->with('success', "{$employee->name} removed.");
    }

    /**
     * Streams a photo or CNIC scan from private storage to the owner.
     */
    public function document(Employee $employee, string $document, EmployeeDocuments $documents): StreamedResponse
    {
        abort_unless($employee->hasDocument($document), 404);

        $path = $employee->{Employee::DOCUMENTS[$document]};

        abort_unless($documents->disk()->exists($path), 404);

        return $documents->disk()->response($path, str($employee->name)->slug().'-'.$document.'.'.pathinfo($path, PATHINFO_EXTENSION), [
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
