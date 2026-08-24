<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Employee;
use App\Settings\RegistrationSettings;
use App\Support\LibyanNationalId;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();

    Storage::fake('local');
});

it('blocks continuing from beneficiaries when a non-libyan husband and libyan children are both present', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Female, 1981);
    $sonNationalId = LibyanNationalId::generate(Gender::Male, 2012);

    Employee::factory()->create([
        'employee_number' => '9401',
        'national_id' => $employeeNationalId,
        'full_name' => 'موظفة متابعة',
        'workplace' => 'tripoli',
    ]);

    $husbandPhoto = UploadedFile::fake()->image('husband.jpg');
    $sonPhoto = UploadedFile::fake()->image('son.jpg');

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('step', 4)
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'زوج أجنبي')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', false)
        ->set('beneficiaryNationality', 'tunisian')
        ->set('beneficiaryPassportNumber', 'TN778899')
        ->set('beneficiaryDateOfBirth', '1978-07-07')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', $husbandPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors();

    // Simulate an inconsistent family state (e.g. older draft / admin data).
    $beneficiaries = $component->get('beneficiaries');
    $beneficiaries[] = [
        'full_name' => 'ابن ليبي',
        'relationship' => BeneficiaryRelationship::Son->value,
        'is_libyan' => true,
        'nationality' => null,
        'national_id' => $sonNationalId,
        'passport_number' => null,
        'date_of_birth' => '2012-01-01',
        'blood_type' => 'o_positive',
        'has_chronic_condition' => false,
        'has_chronic_conditions' => false,
        'chronic_conditions' => [],
        'has_tumor' => false,
        'has_surgery_history' => false,
        'uses_medical_devices' => false,
        'hospitalized_recently' => false,
        'traveled_for_treatment' => false,
        'photo_path' => $sonPhoto->store('registrations/test', 'local'),
    ];

    $component
        ->set('beneficiaries', $beneficiaries)
        ->call('continueFromBeneficiaries')
        ->assertHasErrors(['beneficiaries'])
        ->assertSet('step', 4)
        ->assertSee('لأن الزوج غير ليبي لا يمكن أن يكون الأبناء ليبيين');
});

it('allows continuing when non-libyan husband children are also non-libyan', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Female, 1985);

    Employee::factory()->create([
        'employee_number' => '9402',
        'national_id' => $employeeNationalId,
        'full_name' => 'موظفة صحيحة',
        'workplace' => 'sebha',
    ]);

    $husbandPhoto = UploadedFile::fake()->image('husband.jpg');
    $daughterPhoto = UploadedFile::fake()->image('daughter.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('step', 4)
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'زوج أجنبي')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', false)
        ->set('beneficiaryNationality', 'egyptian')
        ->set('beneficiaryPassportNumber', 'EG556677')
        ->set('beneficiaryDateOfBirth', '1980-03-03')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', $husbandPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'ابنة')
        ->set('beneficiaryRelationship', 'daughter')
        ->set('beneficiaryNationality', 'egyptian')
        ->set('beneficiaryPassportNumber', 'EG667788')
        ->set('beneficiaryDateOfBirth', '2014-04-04')
        ->set('beneficiaryBloodType', 'b_positive')
        ->set('beneficiaryPhoto', $daughterPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->call('continueFromBeneficiaries')
        ->assertHasNoErrors()
        ->assertSet('step', 5);
});
