<?php

use App\Enums\BeneficiaryCancellationReason;
use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Support\LibyanNationalId;
use Illuminate\Support\Facades\Storage;

it('cancels family members who are already employees instead of deleting them', function () {
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
        ->expectsOutputToContain('Cancelled 2 employee-as-family record(s) and deleted 0 duplicate family record(s).')
        ->assertSuccessful();

    $wifeAsFamily = Beneficiary::queryWithCancelled()->find($wifeAsFamily->id);
    $husbandAsFamily = Beneficiary::queryWithCancelled()->find($husbandAsFamily->id);

    expect(Beneficiary::query()->find($wifeAsFamily->id))->toBeNull()
        ->and(Beneficiary::query()->find($husbandAsFamily->id))->toBeNull()
        ->and($wifeAsFamily->isCancelled())->toBeTrue()
        ->and($wifeAsFamily->cancellation_reason)->toBe(BeneficiaryCancellationReason::EmployeeDuplicate)
        ->and($husbandAsFamily->isCancelled())->toBeTrue()
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
        ->expectsOutputToContain('Cancelled 0 employee-as-family record(s) and deleted 1 duplicate family record(s).')
        ->assertSuccessful();

    expect(Beneficiary::query()->find($keep->id))->not->toBeNull()
        ->and(Beneficiary::query()->find($remove->id))->toBeNull()
        ->and($brotherA->fresh()->beneficiaries_count)->toBe(1)
        ->and($brotherB->fresh()->beneficiaries_count)->toBe(0);
});

it('treats a child with the same name and birth date under both parents as one person', function () {
    $father = MedicalRegistration::factory()->submitted()->create(['beneficiaries_count' => 1]);
    $mother = MedicalRegistration::factory()->approved()->create(['beneficiaries_count' => 1]);

    $printed = Beneficiary::factory()->create([
        'medical_registration_id' => $mother->id,
        'full_name' => 'سميحة مجدي خليل بالقاسم',
        'relationship' => BeneficiaryRelationship::Daughter,
        'national_id' => '219980319963',
        'date_of_birth' => '1998-09-14',
        'card_printed_at' => now(),
    ]);
    $mistyped = Beneficiary::factory()->create([
        'medical_registration_id' => $father->id,
        'full_name' => 'سميحه مجدى خليل  بالقاسم',
        'relationship' => BeneficiaryRelationship::Daughter,
        'national_id' => '219980319966',
        'date_of_birth' => '1998-09-14',
    ]);

    $this->artisan('beneficiaries:cleanup-duplicates', ['--force' => true])
        ->expectsOutputToContain('Cancelled 0 employee-as-family record(s) and deleted 1 duplicate family record(s).')
        ->assertSuccessful();

    expect(Beneficiary::query()->find($printed->id))->not->toBeNull()
        ->and(Beneficiary::queryWithCancelled()->find($mistyped->id))->toBeNull()
        ->and($father->fresh()->beneficiaries_count)->toBe(0);
});

it('keeps people with the same name but a different birth date', function () {
    $first = MedicalRegistration::factory()->approved()->create();
    $second = MedicalRegistration::factory()->approved()->create();

    foreach ([[$first, '2010-01-01'], [$second, '2012-05-05']] as [$registration, $birthDate]) {
        Beneficiary::factory()->create([
            'medical_registration_id' => $registration->id,
            'full_name' => 'محمد علي سالم',
            'relationship' => BeneficiaryRelationship::Son,
            'date_of_birth' => $birthDate,
        ]);
    }

    $this->artisan('beneficiaries:cleanup-duplicates', ['--force' => true])
        ->expectsOutputToContain('No duplicate family members found.')
        ->assertSuccessful();

    expect(Beneficiary::query()->count())->toBe(2);
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
        'full_name' => $employeeRegistration->employee->full_name,
        'national_id' => $employeeRegistration->employee->national_id,
        'photo_path' => $path,
    ]);

    $this->artisan('beneficiaries:cleanup-duplicates', ['--dry-run' => true])
        ->expectsOutputToContain('Would cancel 1 employee-as-family record(s) and would delete 0 duplicate family record(s).')
        ->assertSuccessful();

    expect(Beneficiary::query()->find($family->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('leaves beneficiaries passed with --except untouched', function () {
    $employeeRegistration = MedicalRegistration::factory()->approved()->create();
    $otherRegistration = MedicalRegistration::factory()->approved()->create([
        'beneficiaries_count' => 2,
    ]);

    $skipped = Beneficiary::factory()->create([
        'medical_registration_id' => $otherRegistration->id,
        'national_id' => $employeeRegistration->employee->national_id,
    ]);

    $this->artisan('beneficiaries:cleanup-duplicates', ['--force' => true, '--except' => [(string) $skipped->id]])
        ->expectsOutputToContain('No duplicate family members found.')
        ->assertSuccessful();

    expect(Beneficiary::query()->find($skipped->id))->not->toBeNull();
});
