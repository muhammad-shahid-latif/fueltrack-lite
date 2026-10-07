<?php

namespace Database\Factories;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_date' => today(),
            'category' => fake()->randomElement(ExpenseCategory::manual()),
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(500, 10000),
            'payment_method' => PaymentMethod::Cash,
        ];
    }
}
