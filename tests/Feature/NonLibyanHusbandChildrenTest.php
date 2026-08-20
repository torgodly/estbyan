<?php

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

it('forces children of a female employee to be non-libyan when the husband is non-libyan', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Female, 1984);

    Employee::factory()->create([
        'employee_number' => '9201',
        'national_id' => $employeeNationalId,
        'full_name' => 'نورة الموظفة',
        'workplace' => 'tripoli',
    ]);

    $husbandPhoto = UploadedFile::fake()->image('husband.jpg');
    $childPhoto = UploadedFile::fake()->image('son.jpg');

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'زوج أجنبي')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', false)
        ->set('beneficiaryNationality', 'egyptian')
        ->set('beneficiaryPassportNumber', 'EG998877')
        ->set('beneficiaryDateOfBirth', '1980-02-11')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', $husbandPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors();

    $component
        ->set('step', 4)
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الابن')
        ->set('beneficiaryRelationship', 'son')
        ->assertSet('beneficiaryIsLibyan', false)
        ->assertSee('لأن الزوج غير ليبي')
        ->set('beneficiaryNationality', 'egyptian')
        ->set('beneficiaryPassportNumber', 'CH123456')
        ->set('beneficiaryDateOfBirth', '2012-08-01')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', $childPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 2);

    $children = collect($component->get('beneficiaries'))
        ->where('relationship', 'son')
        ->values();

    expect($children)->toHaveCount(1)
        ->and($children[0]['is_libyan'])->toBeFalse()
        ->and($children[0]['passport_number'])->toBe('CH123456')
        ->and($children[0]['national_id'])->toBeNull();
});

it('blocks saving a non-libyan husband while libyan children already exist', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Female, 1983);
    $sonNationalId = LibyanNationalId::generate(Gender::Male, 2010);

    Employee::factory()->create([
        'employee_number' => '9202',
        'national_id' => $employeeNationalId,
        'full_name' => 'هدى',
        'workplace' => 'benghazi',
    ]);

    $sonPhoto = UploadedFile::fake()->image('libyan-son.jpg');
    $husbandPhoto = UploadedFile::fake()->image('foreign-husband.jpg');

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'ابن ليبي')
        ->set('beneficiaryRelationship', 'son')
        ->set('beneficiaryIsLibyan', true)
        ->set('beneficiaryNationalId', $sonNationalId)
        ->set('beneficiaryDateOfBirth', '2010-03-20')
        ->set('beneficiaryBloodType', 'b_positive')
        ->set('beneficiaryPhoto', $sonPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors();

    $component
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'زوج غير ليبي')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', false)
        ->set('beneficiaryNationality', 'tunisian')
        ->set('beneficiaryPassportNumber', 'TN445566')
        ->set('beneficiaryDateOfBirth', '1979-09-09')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', $husbandPhoto)
        ->call('saveBeneficiary')
        ->assertHasErrors(['beneficiaryIsLibyan']);

    expect(collect($component->get('beneficiaries'))->where('relationship', 'spouse'))->toHaveCount(0);
});

it('still allows libyan children when a male employee has a non-libyan wife', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1978);
    $sonNationalId = LibyanNationalId::generate(Gender::Male, 2011);

    Employee::factory()->create([
        'employee_number' => '9203',
        'national_id' => $employeeNationalId,
        'full_name' => 'كريم',
        'workplace' => 'misrata',
    ]);

    $wifePhoto = UploadedFile::fake()->image('wife.jpg');
    $sonPhoto = UploadedFile::fake()->image('son.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'زوجة أجنبية')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', false)
        ->set('beneficiaryNationality', 'egyptian')
        ->set('beneficiaryPassportNumber', 'EG112233')
        ->set('beneficiaryDateOfBirth', '1985-01-15')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', $wifePhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'ابن ليبي')
        ->set('beneficiaryRelationship', 'son')
        ->assertSet('beneficiaryIsLibyan', true)
        ->set('beneficiaryNationalId', $sonNationalId)
        ->set('beneficiaryDateOfBirth', '2011-04-04')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', $sonPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 2);
});
