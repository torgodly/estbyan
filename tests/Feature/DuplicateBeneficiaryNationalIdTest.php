<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Settings\RegistrationSettings;
use App\Support\InsuranceCardNumber;
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

it('rejects two daughters with the same national id on one registration', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1978);
    $daughterNationalId = LibyanNationalId::generate(Gender::Female, 2005);

    Employee::factory()->create([
        'employee_number' => '9401',
        'national_id' => $employeeNationalId,
        'full_name' => 'الأب الموظف',
        'workplace' => 'tripoli',
    ]);

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الابنة الأولى')
        ->set('beneficiaryRelationship', 'daughter')
        ->set('beneficiaryNationalId', $daughterNationalId)
        ->set('beneficiaryDateOfBirth', '2005-03-15')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('first-daughter.jpg'))
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 1);

    $component
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الابنة الثانية')
        ->set('beneficiaryRelationship', 'daughter')
        ->set('beneficiaryNationalId', $daughterNationalId)
        ->set('beneficiaryDateOfBirth', '2005-03-15')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('second-daughter.jpg'))
        ->call('saveBeneficiary')
        ->assertHasErrors(['beneficiaryNationalId'])
        ->assertCount('beneficiaries', 1);

    expect(Beneficiary::query()->where('national_id', $daughterNationalId)->count())->toBe(1);
});

it('rejects a second employee adding a mother that is already registered', function () {
    $firstEmployeeNationalId = LibyanNationalId::generate(Gender::Male, 1980);
    $secondEmployeeNationalId = LibyanNationalId::generate(Gender::Male, 1982);
    $motherNationalId = LibyanNationalId::generate(Gender::Female, 1958);

    $firstEmployee = Employee::factory()->create([
        'employee_number' => '9402',
        'national_id' => $firstEmployeeNationalId,
        'full_name' => 'الأخ الأكبر',
        'workplace' => 'tripoli',
    ]);

    $firstRegistration = MedicalRegistration::factory()->create([
        'employee_id' => $firstEmployee->id,
        'employee_number' => $firstEmployee->employee_number,
        'national_id' => $firstEmployeeNationalId,
        'full_name' => $firstEmployee->full_name,
    ]);

    Beneficiary::factory()->create([
        'medical_registration_id' => $firstRegistration->id,
        'full_name' => 'الأم المشتركة',
        'relationship' => 'mother',
        'national_id' => $motherNationalId,
        'date_of_birth' => '1958-04-10',
    ]);

    Employee::factory()->create([
        'employee_number' => '9403',
        'national_id' => $secondEmployeeNationalId,
        'full_name' => 'الأخ الأصغر',
        'workplace' => 'benghazi',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $secondEmployeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'أمي أيضاً')
        ->set('beneficiaryRelationship', 'mother')
        ->set('beneficiaryNationalId', $motherNationalId)
        ->set('beneficiaryDateOfBirth', '1958-04-10')
        ->set('beneficiaryBloodType', 'b_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('same-mother.jpg'))
        ->call('saveBeneficiary')
        ->assertHasErrors(['beneficiaryNationalId'])
        ->assertSee('هذا الرقم الوطني مسجّل بالفعل ضمن عائلة '.$firstEmployee->full_name)
        ->assertCount('beneficiaries', 0);

    expect(Beneficiary::query()->where('national_id', $motherNationalId)->count())->toBe(1);
});

it('rejects using the employee national id as a beneficiary', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1975);

    Employee::factory()->create([
        'employee_number' => '9404',
        'national_id' => $employeeNationalId,
        'full_name' => 'الموظف نفسه',
        'workplace' => 'misrata',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'أنا')
        ->set('beneficiaryRelationship', 'father')
        ->set('beneficiaryNationalId', $employeeNationalId)
        ->set('beneficiaryDateOfBirth', '1975-01-01')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('self.jpg'))
        ->call('saveBeneficiary')
        ->assertHasErrors(['beneficiaryNationalId'])
        ->assertCount('beneficiaries', 0);
});

it('allows editing a beneficiary without changing the national id', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1976);
    $daughterNationalId = LibyanNationalId::generate(Gender::Female, 2001);

    Employee::factory()->create([
        'employee_number' => '9405',
        'national_id' => $employeeNationalId,
        'full_name' => 'أب يعدّل ابنته',
        'workplace' => 'tripoli',
    ]);

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الابنة')
        ->set('beneficiaryRelationship', 'daughter')
        ->set('beneficiaryNationalId', $daughterNationalId)
        ->set('beneficiaryDateOfBirth', '2001-08-20')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('daughter.jpg'))
        ->call('saveBeneficiary')
        ->assertHasNoErrors();

    $component
        ->call('editBeneficiary', 0)
        ->set('beneficiaryBloodType', 'ab_positive')
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 1);

    expect(Beneficiary::query()->where('national_id', $daughterNationalId)->count())->toBe(1)
        ->and(Beneficiary::query()->where('national_id', $daughterNationalId)->first()->blood_type->value)
        ->toBe('ab_positive');
});

it('issues a new card number when a declined registration corrects a duplicate son national id', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1974);
    $sharedSonNationalId = LibyanNationalId::generate(Gender::Male, 2008);
    $correctedSonNationalId = LibyanNationalId::generate(Gender::Male, 2008);

    $employee = Employee::factory()->create([
        'employee_number' => '9406',
        'national_id' => $employeeNationalId,
        'full_name' => 'أب مرفوض',
        'workplace' => 'tripoli',
    ]);

    $registration = MedicalRegistration::factory()->declined()->create([
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'national_id' => $employeeNationalId,
        'full_name' => $employee->full_name,
        'workplace' => 'tripoli',
        'marital_status' => 'married',
        'review_note' => 'رقم وطني مكرر لأحد الأبناء',
        'beneficiaries_count' => 2,
    ]);

    $firstSon = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'الابن الأول',
        'relationship' => BeneficiaryRelationship::Son,
        'national_id' => $sharedSonNationalId,
        'date_of_birth' => '2008-01-01',
    ]);

    $secondSon = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'الابن الثاني',
        'relationship' => BeneficiaryRelationship::Son,
        'national_id' => $sharedSonNationalId,
        'date_of_birth' => '2008-01-01',
    ]);

    $sharedCard = $firstSon->card_number;

    expect($secondSon->card_number)->toBe($sharedCard);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->assertSet('submitted', false)
        ->set('step', 4)
        ->set('maritalStatus', 'married')
        ->call('editBeneficiary', 1)
        ->set('beneficiaryNationalId', $correctedSonNationalId)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 2);

    $beneficiaries = $registration->fresh('beneficiaries')->beneficiaries;
    $unchanged = $beneficiaries->firstWhere('national_id', $sharedSonNationalId);
    $corrected = $beneficiaries->firstWhere('national_id', $correctedSonNationalId);

    expect($unchanged)->not->toBeNull()
        ->and($corrected)->not->toBeNull()
        ->and($unchanged->card_number)->toBe($sharedCard)
        ->and($corrected->card_number)->not->toBe($sharedCard)
        ->and(InsuranceCardNumber::isCurrent($corrected->card_number))->toBeTrue();
});
