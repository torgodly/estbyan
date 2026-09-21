<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Support\LibyanNationalId;
use Illuminate\Support\Facades\Storage;

it('removes family members who are already employees', function () {
    $husband = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'أحمد الزوج',
        'gender' => Gender::Male,
        'beneficiaries_count' => 1,
    ]);
    $wife = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'فاطمة الزوجة',
        'gender' => Gender::Female,
        'beneficiaries_count' => 1,
    ]);

    $wifeAsFamily = Beneficiary::factory()->create([
        'medical_registration_id' => $husband->id,
        'full_name' => $wife->employee->full_name,
        'relationship' => BeneficiaryRelationship::Spouse,
        'national_id' => $wife->employee->national_id,
    ]);
    $husbandAsFamily = Beneficiary::factory()->create([
        'medical_registration_id' => $wife->id,
        'full_name' => $husband->employee->full_name,
        'relationship' => BeneficiaryRelationship::Spouse,
        'national_id' => $husband->employee->national_id,
    ]);

    $this->artisan('beneficiaries:cleanup-duplicates', ['--force' => true])
        ->expectsOutputToContain('Deleted 2 employee-as-family record(s) and 0 duplicate family record(s).')
        ->assertSuccessful();

    expect(Beneficiary::query()->find($wifeAsFamily->id))->toBeNull()
        ->and(Beneficiary::query()->find($husbandAsFamily->id))->toBeNull()
        ->and($husband->employee->fresh())->not->toBeNull()
        ->and($wife->employee->fresh())->not->toBeNull()
        ->and($husband->fresh()->beneficiaries_count)->toBe(0)
        ->and($wife->fresh()->beneficiaries_count)->toBe(0);
});

it('keeps only one family record when the same person was added under multiple employees', function () {
    $brotherA = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'خالد الأخ',
        'beneficiaries_count' => 1,
    ]);
    $brotherB = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'سامي الأخ',
        'beneficiaries_count' => 1,
    ]);

    $motherNationalId = LibyanNationalId::generate(Gender::Female, 1965);

    $keep = Beneficiary::factory()->create([
        'medical_registration_id' => $brotherA->id,
        'full_name' => 'منى الأم',
        'relationship' => BeneficiaryRelationship::Mother,
        'national_id' => $motherNationalId,
        'card_printed_at' => now(),
    ]);
    $remove = Beneficiary::factory()->create([
        'medical_registration_id' => $brotherB->id,
        'full_name' => 'منى الأم',
        'relationship' => BeneficiaryRelationship::Mother,
        'national_id' => $motherNationalId,
    ]);

    $this->artisan('beneficiaries:cleanup-duplicates', ['--force' => true])
        ->expectsOutputToContain('Deleted 0 employee-as-family record(s) and 1 duplicate family record(s).')
        ->assertSuccessful();

    expect(Beneficiary::query()->find($keep->id))->not->toBeNull()
        ->and(Beneficiary::query()->find($remove->id))->toBeNull()
        ->and($brotherA->fresh()->beneficiaries_count)->toBe(1)
        ->and($brotherB->fresh()->beneficiaries_count)->toBe(0);
});

it('does not delete unique family members', function () {
    $registration = MedicalRegistration::factory()->approved()->create([
        'beneficiaries_count' => 1,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);

    $this->artisan('beneficiaries:cleanup-duplicates', ['--force' => true])
        ->expectsOutputToContain('No duplicate family members found.')
        ->assertSuccessful();

    expect(Beneficiary::query()->find($spouse->id))->not->toBeNull()
        ->and($registration->fresh()->beneficiaries_count)->toBe(1);
});

it('supports dry-run without deleting records', function () {
    Storage::fake('local');

    $employeeRegistration = MedicalRegistration::factory()->approved()->create();
    $otherRegistration = MedicalRegistration::factory()->approved()->create([
        'beneficiaries_count' => 1,
    ]);

    $path = 'registrations/duplicate-spouse.jpg';
    Storage::disk('local')->put($path, 'photo');

    $family = Beneficiary::factory()->create([
        'medical_registration_id' => $otherRegistration->id,
        'national_id' => $employeeRegistration->employee->national_id,
        'photo_path' => $path,
    ]);

    $this->artisan('beneficiaries:cleanup-duplicates', ['--dry-run' => true])
        ->expectsOutputToContain('Would delete 1 employee-as-family record(s) and 0 duplicate family record(s).')
        ->assertSuccessful();

    expect(Beneficiary::query()->find($family->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});
