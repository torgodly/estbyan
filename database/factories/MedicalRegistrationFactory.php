<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MedicalRegistration>
 */
class MedicalRegistrationFactory extends Factory
{
    protected $model = MedicalRegistration::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'employee_number' => (string) fake()->unique()->numberBetween(1000, 99999),
            'national_id' => fake()->unique()->numerify('#############'),
            'full_name' => fake()->name(),
            'workplace' => 'general_admin',
            'status' => RegistrationStatus::Draft,
            'current_step' => 2,
            'consent_at' => now(),
            'date_of_birth' => '1990-01-01',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (MedicalRegistration $registration): void {
            if (! $registration->employee_id) {
                return;
            }

            $employee = $registration->employee ?? Employee::query()->find($registration->employee_id);

            if (! $employee) {
                return;
            }

            $registration->employee_number = $registration->employee_number ?: $employee->employee_number;
            $registration->national_id = $registration->national_id ?: $employee->national_id;
            $registration->full_name = $registration->full_name ?: $employee->full_name;
            $registration->workplace = $registration->workplace ?: $employee->workplace;
        });
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => RegistrationStatus::Submitted,
            'submitted_at' => now(),
            'reference_number' => 'SC'.now()->format('y').'-'.fake()->unique()->numerify('#####'),
            'family_status_document_path' => 'registrations/demo/family.pdf',
            'employee_photo_path' => 'registrations/demo/employee.jpg',
            'current_step' => 6,
        ]);
    }

    public function approved(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => RegistrationStatus::Approved,
            'reviewed_at' => now(),
        ]);
    }

    public function declined(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => RegistrationStatus::Declined,
            'review_note' => 'بيانات ناقصة',
            'reviewed_at' => now(),
        ]);
    }

    public function editing(): static
    {
        return $this->submitted()->state(fn (): array => [
            'status' => RegistrationStatus::Editing,
            'current_step' => 2,
        ]);
    }
}
