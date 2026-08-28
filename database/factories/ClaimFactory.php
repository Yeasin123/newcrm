<?php

namespace Database\Factories;

use App\Models\Claim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Claim>
 */
class ClaimFactory extends Factory
{
    protected $model = Claim::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => 0,
            'claim_no' => (string) fake()->unique()->numerify('CLM-#####'),
            'system' => 0,
            'brand' => 0,
            'problem' => fake()->sentence(),
            'status' => 0,
            'owed_price' => 0,
            'total_price' => 0,
            'is_callfee' => false,
        ];
    }
}
