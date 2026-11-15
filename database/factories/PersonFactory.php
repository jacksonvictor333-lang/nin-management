<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        $dob = fake()->dateTimeBetween('-70 years', '-18 years');

        return [
            'nin' => Person::generateNin($dob),
            'first_name' => fake()->firstName(),
            'middle_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => $dob->format('Y-m-d'),
            'gender' => fake()->randomElement(['Male', 'Female']),
            'nationality' => 'Tanzanian',
            'phone' => '07' . fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'status' => 'Active',
        ];
    }
}
