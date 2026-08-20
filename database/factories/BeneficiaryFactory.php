<?php

namespace Database\Factories;

use App\Enums\BeneficiaryRelationship;
use App\Enums\BloodType;
use App\Enums\Gender;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Support\LibyanNationalId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    protected $model = Beneficiary::class;

    public function definition(): array
    {
        $birthYear = fake()->numberBetween(1995, 2015);

        return [
            'medical_registration_id' => MedicalRegistration::factory(),
            'full_name' => fake()->name(),
            'relationship' => BeneficiaryRelationship::Spouse,
            'is_libyan' => true,
            'nationality' => null,
            'national_id' => LibyanNationalId::generate(Gender::Female, $birthYear),
            'passport_number' => null,
            'date_of_birth' => sprintf('%d-%02d-%02d', $birthYear, fake()->numberBetween(1, 12), fake()->numberBetween(1, 28)),
            'blood_type' => BloodType::APositive,
            'has_chronic_condition' => false,
            'has_chronic_conditions' => false,
            'chronic_conditions' => null,
            'has_tumor' => false,
            'has_surgery_history' => false,
            'uses_medical_devices' => false,
            'hospitalized_recently' => false,
            'traveled_for_treatment' => false,
            'photo_path' => 'registrations/demo/beneficiary.jpg',
        ];
    }
}
