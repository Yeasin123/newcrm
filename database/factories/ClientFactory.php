<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'policy_number' => 'AHC-'.fake()->unique()->numerify('#####'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'contact' => fake()->numerify('##########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
            'zip_code' => fake()->postcode(),
            'payment_structure' => fake()->randomElement(['1', '2']),
            'salesman_name' => fake()->randomElement(['Kevin', 'Allen', 'Jacob Martin']),
            'status' => 1,
            'login_off' => false,
            'star' => false,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn () => ['payment_structure' => '1']);
    }

    public function yearly(): static
    {
        return $this->state(fn () => ['payment_structure' => '2']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 0]);
    }
}
