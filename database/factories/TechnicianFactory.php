<?php

namespace Database\Factories;

use App\Models\Technician;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Technician>
 */
class TechnicianFactory extends Factory
{
    protected $model = Technician::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'business_name' => fake()->company(),
            'contact' => fake()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'city' => ['Austin', 'Dallas'],
            'state' => ['TX'],
            'services' => ['HVAC', 'Plumbing'],
            'call_fee' => 75,
            'in_ex' => false,
        ];
    }
}
