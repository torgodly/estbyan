<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Support\LibyanNationalId;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('allows only spouse and mother to be non-libyan', function () {
    expect(BeneficiaryRelationship::Spouse->allowsNonLibyan())->toBeTrue()
        ->and(BeneficiaryRelationship::Mother->allowsNonLibyan())->toBeTrue()
        ->and(BeneficiaryRelationship::Father->allowsNonLibyan())->toBeFalse()
        ->and(BeneficiaryRelationship::Son->allowsNonLibyan())->toBeFalse()
        ->and(BeneficiaryRelationship::Daughter->allowsNonLibyan())->toBeFalse();
});

it('saves a non-libyan wife with nationality and passport instead of national id', function () {
    Storage::fake('local');

    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1980);

    Employee::factory()->create([
        'employee_number' => '8801',
        'national_id' => $employeeNationalId,
        'full_name' => 'محمد المتزوج',
        'workplace' => 'general_admin',
    ]);

    $photo = UploadedFile::fake()->image('wife.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'فاطمة الأجنبية')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', false)
        ->set('beneficiaryNationality', 'egyptian')
        ->set('beneficiaryPassportNumber', 'A1234567')
        ->set('beneficiaryDateOfBirth', '1985-06-20')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 1)
        ->assertSee('مصر')
        ->assertSee('جواز: A1234567');

    $beneficiary = Beneficiary::query()->first();

    expect($beneficiary)->not->toBeNull()
        ->and($beneficiary->is_libyan)->toBeFalse()
        ->and($beneficiary->nationality)->toBe('egyptian')
        ->and($beneficiary->passport_number)->toBe('A1234567')
        ->and($beneficiary->national_id)->toBeNull()
        ->and($beneficiary->nationalityLabel())->toBe('مصر')
        ->and($beneficiary->identityLabel())->toContain('جواز: A1234567');
});

it('saves a non-libyan mother with passport and rejects sons without national id', function () {
    Storage::fake('local');

    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1982);

    Employee::factory()->create([
        'employee_number' => '8802',
        'national_id' => $employeeNationalId,
        'full_name' => 'سالم الأعزب',
        'workplace' => 'tripoli',
    ]);

    $motherPhoto = UploadedFile::fake()->image('mother.jpg');
    $sonPhoto = UploadedFile::fake()->image('son.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'خديجة الأم')
        ->set('beneficiaryRelationship', 'mother')
        ->set('beneficiaryIsLibyan', false)
        ->set('beneficiaryNationality', 'tunisian')
        ->set('beneficiaryPassportNumber', 'TN998877')
        ->set('beneficiaryDateOfBirth', '1960-01-10')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', $motherPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'علي الابن')
        ->set('beneficiaryRelationship', 'son')
        ->assertSet('beneficiaryIsLibyan', true)
        ->set('beneficiaryPassportNumber', 'X123')
        ->set('beneficiaryDateOfBirth', '2010-02-02')
        ->set('beneficiaryBloodType', 'b_positive')
        ->set('beneficiaryPhoto', $sonPhoto)
        ->call('saveBeneficiary')
        ->assertHasErrors('beneficiaryNationalId');

    expect(Beneficiary::query()->where('relationship', 'mother')->value('passport_number'))->toBe('TN998877')
        ->and(Beneficiary::query()->where('relationship', 'son')->exists())->toBeFalse();
});

it('requires nationality and passport when spouse is marked non-libyan', function () {
    Storage::fake('local');

    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1978);

    Employee::factory()->create([
        'employee_number' => '8803',
        'national_id' => $employeeNationalId,
        'full_name' => 'عمر التسجيل',
        'workplace' => 'sebha',
    ]);

    $photo = UploadedFile::fake()->image('spouse.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'زوجة ناقصة')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', false)
        ->set('beneficiaryDateOfBirth', '1988-03-15')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasErrors(['beneficiaryNationality', 'beneficiaryPassportNumber']);
});

it('exposes a searchable nationality list with many countries', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1975);

    Employee::factory()->create([
        'employee_number' => '8804',
        'national_id' => $employeeNationalId,
        'full_name' => 'موظف الجنسيات',
        'workplace' => 'tripoli',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', false)
        ->assertSeeHtml('ابحث عن الجنسية')
        ->assertSee('فرنسا')
        ->assertSee('تركيا')
        ->assertSee('تشاد')
        ->assertSee('أخرى');

    expect(config('registration.nationalities'))->toHaveKey('egyptian')
        ->and(config('registration.nationalities'))->toHaveKey('french')
        ->and(count(config('registration.nationalities')))->toBeGreaterThan(80);

    $priority = config('registration.nationality_priority');
    expect($priority[0])->toBe('egyptian')
        ->and($priority[1])->toBe('tunisian')
        ->and($priority)->toContain('chadian');

    $orderedKeys = array_keys(
        Livewire::test(MedicalRegistrationForm::class)
            ->set('nationalId', $employeeNationalId)
            ->set('consent', true)
            ->call('verifyIdentity')
            ->viewData('nationalities')
    );

    expect($orderedKeys[0])->toBe('egyptian')
        ->and($orderedKeys[1])->toBe('tunisian')
        ->and($orderedKeys[2])->toBe('algerian')
        ->and(array_slice($orderedKeys, 0, 3))->not->toContain('afghan');
});
