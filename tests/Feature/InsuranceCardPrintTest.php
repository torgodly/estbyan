<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Filament\Resources\MedicalRegistrations\Pages\ViewMedicalRegistration;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Settings\RegistrationSettings;
use App\Support\LibyanNationalId;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('does not mark cards as printed when the pack is printed', function () {
    $admin = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();
    $beneficiary = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'ليلى أحمد',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->callAction('printInsuranceCards')
        ->assertHasNoActionErrors()
        ->assertSee('طُبع 0 من 2');

    expect($registration->employee->fresh()->cardIsPrinted())->toBeFalse()
        ->and($beneficiary->fresh()->cardIsPrinted())->toBeFalse();
});

it('toggles only the selected family card', function () {
    $admin = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $child = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Daughter,
    ]);

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->call('toggleInsuranceCardPrinted', 'beneficiary-'.$spouse->id)
        ->assertSee('طُبع 1 من 3');

    expect($registration->employee->fresh()->cardIsPrinted())->toBeFalse()
        ->and($spouse->fresh()->cardIsPrinted())->toBeTrue()
        ->and($child->fresh()->cardIsPrinted())->toBeFalse();
});

it('can clear a printed mark without printing again', function () {
    $admin = User::factory()->smartCare()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();
    $registration->employee->markCardPrinted();

    $this->actingAs($admin);

    Livewire::test(ViewMedicalRegistration::class, ['record' => $registration->getRouteKey()])
        ->assertSee('طُبعت')
        ->call('toggleInsuranceCardPrinted', 'employee')
        ->assertSee('لم تُطبع');

    expect($registration->employee->fresh()->cardIsPrinted())->toBeFalse();
});

it('keeps the printed mark when a family member is edited on the form', function () {
    Storage::fake('local');

    $settings = app(RegistrationSettings::class);
    $settings->form_enabled = true;
    $settings->save();

    $employeeNationalId = LibyanNationalId::generate(Gender::Female, 1989);
    $beneficiaryNationalId = LibyanNationalId::generate(Gender::Male, 1988);

    Employee::factory()->create([
        'employee_number' => '6103',
        'national_id' => $employeeNationalId,
        'full_name' => 'نادية حسن',
        'workplace' => 'general_admin',
    ]);

    $photo = UploadedFile::fake()->image('spouse.jpg');

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('employeeNumber', '6103')
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'محمد حسن')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryNationalId', $beneficiaryNationalId)
        ->set('beneficiaryDateOfBirth', '1988-03-15')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryHasChronicConditions', false)
        ->set('beneficiaryHasTumor', false)
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasNoErrors();

    $registration = MedicalRegistration::query()->where('employee_number', '6103')->first();
    $original = $registration->beneficiaries()->first();
    $original->markCardPrinted();

    $component
        ->call('editBeneficiary', 0)
        ->set('beneficiaryBloodType', 'b_positive')
        ->call('saveBeneficiary')
        ->assertHasNoErrors();

    $updated = $registration->fresh('beneficiaries')->beneficiaries->first();

    expect($updated->cardIsPrinted())->toBeTrue()
        ->and($updated->card_number)->toBe($original->card_number);
});

it('copies the printed mark when the same family member is added on another request', function () {
    $registration = MedicalRegistration::factory()->create();
    $first = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'national_id' => '219880112233',
    ]);
    $first->markCardPrinted();

    $secondRegistration = MedicalRegistration::factory()->create([
        'employee_id' => $registration->employee_id,
    ]);

    $second = Beneficiary::factory()->create([
        'medical_registration_id' => $secondRegistration->id,
        'national_id' => '219880112233',
    ]);

    expect($second->card_number)->toBe($first->card_number)
        ->and($second->cardIsPrinted())->toBeTrue();
});
