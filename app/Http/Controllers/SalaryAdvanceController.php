<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Http\Requests\StoreSalaryAdvanceRequest;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Services\PayrollService;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;

class SalaryAdvanceController extends Controller
{
    public function store(StoreSalaryAdvanceRequest $request, Employee $employee, PayrollService $payroll): RedirectResponse
    {
        $advance = $payroll->giveAdvance(
            employee: $employee,
            amount: (float) $request->input('amount'),
            paymentMethod: $request->enum('payment_method', PaymentMethod::class),
            recordedBy: $request->user(),
            notes: $request->input('notes'),
        );

        $slip = $payroll->pendingSlipFor($employee);

        return redirect()
            ->route('employees.show', $employee)
            ->with('success', sprintf(
                'Advance of %s given to %s. %s',
                Format::money($advance->amount),
                $employee->name,
                $slip
                    ? "Added to the {$slip->month->format('F Y')} salary sheet: net salary is now ".Format::money($slip->net_salary).'.'
                    : 'It will be recovered from the next salary.',
            ));
    }

    public function destroy(SalaryAdvance $advance, PayrollService $payroll): RedirectResponse
    {
        $payroll->deleteAdvance($advance);

        return redirect()
            ->route('employees.show', $advance->employee_id)
            ->with('success', 'Advance of '.Format::money($advance->amount).' deleted.');
    }
}
