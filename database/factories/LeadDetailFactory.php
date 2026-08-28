<?php

namespace Database\Factories;

use App\Models\LeadDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadDetail>
 */
class LeadDetailFactory extends Factory
{
    protected $model = LeadDetail::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => 0,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'contact' => fake()->numerify('##########'),
            'email' => fake()->unique()->safeEmail(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip_code' => fake()->postcode(),
            'status' => 0,
            'phone_company' => fake()->randomElement(['Verizon', 'AT&T', 'T-Mobile']),
            'contact_text' => fake()->randomElement(['Cell', 'Landline']),
        ];
    }
}
