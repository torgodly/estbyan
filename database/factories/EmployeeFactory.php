<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Models\Employee;
use App\Support\LibyanNationalId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        $birthYear = fake()->numberBetween(1965, 2000);

        return [
            'employee_number' => (string) fake()->unique()->numberBetween(1000, 99999),
            'national_id' => LibyanNationalId::generate(Gender::Male, $birthYear),
            'date_of_birth' => null,
            'full_name' => fake()->name(),
            'workplace' => 'general_admin',
            'is_active' => true,
        ];
    }
}
