<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'employee_number' => (string) fake()->unique()->numberBetween(1000, 9999),
            'national_id' => fake()->unique()->numerify('#############'),
            'date_of_birth' => fake()->date(),
            'full_name' => fake()->name(),
            'is_active' => true,
        ];
    }
}
