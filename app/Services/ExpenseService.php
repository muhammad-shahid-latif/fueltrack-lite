<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    public function __construct(private DailyClosingService $closings) {}

    /**
     * @throws ValidationException
     */
    public function record(
        DateTimeInterface|string $date,
        ExpenseCategory $category,
        string $description,
        float $amount,
        PaymentMethod $paymentMethod = PaymentMethod::Cash,
        ?User $recordedBy = null,
        ?string $notes = null,
    ): Expense {
        $date = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($date, $category, $description, $amount, $paymentMethod, $recordedBy, $notes) {
            $this->closings->assertOpen($date);

            return Expense::create([
                'business_date' => $date,
                'category' => $category,
                'description' => $description,
                'amount' => round($amount, 2),
                'payment_method' => $paymentMethod,
                'recorded_by' => $recordedBy?->id,
                'notes' => $notes,
            ]);
        });
    }
}
