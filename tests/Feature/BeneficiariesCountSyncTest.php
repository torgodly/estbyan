<?php

use App\Enums\Gender;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Employee;
use App\Models\MedicalRegistration;
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

it('does not show beneficiaries count on the employee details step', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1980);

    Employee::factory()->create([
        'employee_number' => '9301',
        'national_id' => $employeeNationalId,
        'full_name' => 'موظف بدون عداد',
        'workplace' => 'tripoli',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertSet('step', 2)
        ->assertDontSee('عدد المستفيدين')
        ->assertSee('الحالة الاجتماعية');
});

it('syncs beneficiaries_count from the family list when adding and deleting members', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1977);
    $motherNationalId = LibyanNationalId::generate(Gender::Female, 1955);
    $fatherNationalId = LibyanNationalId::generate(Gender::Male, 1950);

    Employee::factory()->create([
        'employee_number' => '9302',
        'national_id' => $employeeNationalId,
        'full_name' => 'موظف العائلة',
        'workplace' => 'benghazi',
    ]);

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('dateOfBirth', '1977-05-10')
        ->set('bloodType', 'o_positive')
        ->set('city', 'benghazi')
        ->set('address', 'بنغازي')
        ->set('phone', '0911112233')
        ->call('saveEmployeeDetails')
        ->assertHasNoErrors();

    $registration = MedicalRegistration::query()->where('employee_number', '9302')->first();

    expect($registration)->not->toBeNull()
        ->and($registration->beneficiaries_count)->toBe(0);

    $motherPhoto = UploadedFile::fake()->image('mother.jpg');

    $component
        ->set('step', 4)
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الأم')
        ->set('beneficiaryRelationship', 'mother')
        ->set('beneficiaryNationalId', $motherNationalId)
        ->set('beneficiaryDateOfBirth', '1955-01-01')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', $motherPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 1)
        ->assertSet('beneficiariesCount', '1');

    expect($registration->fresh()->beneficiaries_count)->toBe(1);

    $fatherPhoto = UploadedFile::fake()->image('father.jpg');

    $component
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الأب')
        ->set('beneficiaryRelationship', 'father')
        ->set('beneficiaryNationalId', $fatherNationalId)
        ->set('beneficiaryDateOfBirth', '1950-02-02')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', $fatherPhoto)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 2)
        ->assertSet('beneficiariesCount', '2');

    expect($registration->fresh()->beneficiaries_count)->toBe(2);

    $component
        ->call('deleteBeneficiary', 0)
        ->assertCount('beneficiaries', 1)
        ->assertSet('beneficiariesCount', '1');

    expect($registration->fresh()->beneficiaries_count)->toBe(1);
});

it('shows the family total on the review step instead of a manual count', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Female, 1982);
    $motherNationalId = LibyanNationalId::generate(Gender::Female, 1960);

    Employee::factory()->create([
        'employee_number' => '9303',
        'national_id' => $employeeNationalId,
        'full_name' => 'موظفة المراجعة',
        'workplace' => 'misrata',
    ]);

    $photo = UploadedFile::fake()->image('mother.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الأم')
        ->set('beneficiaryRelationship', 'mother')
        ->set('beneficiaryNationalId', $motherNationalId)
        ->set('beneficiaryDateOfBirth', '1960-06-06')
        ->set('beneficiaryBloodType', 'b_positive')
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->set('step', 6)
        ->assertSee('عدد المستفيدين')
        ->assertSee('1');
});
