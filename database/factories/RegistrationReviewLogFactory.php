<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;
use App\Models\RegistrationReviewLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RegistrationReviewLog>
 */
class RegistrationReviewLogFactory extends Factory
{
    protected $model = RegistrationReviewLog::class;

    public function definition(): array
    {
        return [
            'medical_registration_id' => MedicalRegistration::factory(),
            'user_id' => User::factory(),
            'action' => fake()->randomElement([
                RegistrationStatus::Approved,
                RegistrationStatus::Declined,
            ]),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
