<?php

namespace Database\Factories;

use App\Enums\ReviewFlagReason;
use App\Models\Employee;
use App\Models\ReviewFlag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewFlag>
 */
class ReviewFlagFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'flaggable_type' => (new Employee)->getMorphClass(),
            'flaggable_id' => Employee::factory(),
            'reason' => ReviewFlagReason::PossibleDuplicateEmployee,
            'note' => fake()->sentence(),
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'resolved_at' => now(),
        ]);
    }
}
