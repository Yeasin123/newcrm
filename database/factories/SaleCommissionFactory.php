<?php

namespace Database\Factories;

use App\Models\SaleCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleCommission>
 */
class SaleCommissionFactory extends Factory
{
    protected $model = SaleCommission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'client_id' => fake()->numberBetween(1, 5000),
            'policy_number' => fake()->numerify('###-###-###'),
            'price' => fake()->randomFloat(2, 300, 1200),
            'commision_price' => fake()->randomFloat(2, 20, 120),
            'store_date' => now(),
            'is_paid' => false,
            'is_cancel' => false,
            'is_cancel_deducted' => false,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['is_paid' => true]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['is_cancel' => true]);
    }
}
