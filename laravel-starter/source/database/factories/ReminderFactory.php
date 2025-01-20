<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reminder>
 */
class ReminderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => $this->faker->unique()->randomNumber(),
            'user' => $this->faker->name(),
            'text' => $this->faker->sentence(),
            'recurrence_type' => $this->faker->randomElement(['daily', 'weekly', 'monthly']),
            'custom_recurrence' => $this->faker->randomNumber(),
            'start_date' => $this->faker->date(),
        ];
    }
}
