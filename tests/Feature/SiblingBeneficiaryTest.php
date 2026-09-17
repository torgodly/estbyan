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

it('lets a single employee add a brother and a sister', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1990);

    Employee::factory()->create([
        'employee_number' => '9201',
        'national_id' => $employeeNationalId,
        'full_name' => 'سالم الأعزب',
        'workplace' => 'tripoli',
    ]);

    $brotherNationalId = LibyanNationalId::generate(Gender::Male, 1992);
    $sisterNationalId = LibyanNationalId::generate(Gender::Female, 1994);

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('maritalStatus', 'single')
        ->set('showBeneficiaryForm', true)
        ->assertSeeHtml('>أخ</option>')
        ->assertSeeHtml('>أخت</option>')
        ->assertDontSeeHtml('>زوجة</option>')
        ->set('beneficiaryName', 'الأخ')
        ->set('beneficiaryRelationship', 'brother')
        ->set('beneficiaryIsLibyan', true)
        ->set('beneficiaryNationalId', $brotherNationalId)
        ->set('beneficiaryDateOfBirth', '1992-03-15')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('brother.jpg'))
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 1);

    $component
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الأخت')
        ->set('beneficiaryRelationship', 'sister')
        ->set('beneficiaryIsLibyan', true)
        ->set('beneficiaryNationalId', $sisterNationalId)
        ->set('beneficiaryDateOfBirth', '1994-07-20')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('sister.jpg'))
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 2);

    expect($component->get('beneficiaries')[0]['relationship'])->toBe('brother')
        ->and($component->get('beneficiaries')[1]['relationship'])->toBe('sister');
});

it('blocks married employees from adding a brother or sister', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1985);

    Employee::factory()->create([
        'employee_number' => '9202',
        'national_id' => $employeeNationalId,
        'full_name' => 'عمر المتزوج',
        'workplace' => 'benghazi',
    ]);

    $brotherNationalId = LibyanNationalId::generate(Gender::Male, 1988);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->assertDontSeeHtml('>أخ</option>')
        ->assertDontSeeHtml('>أخت</option>')
        ->set('beneficiaryName', 'أخ غير مسموح')
        ->set('beneficiaryRelationship', 'brother')
        ->set('beneficiaryIsLibyan', true)
        ->set('beneficiaryNationalId', $brotherNationalId)
        ->set('beneficiaryDateOfBirth', '1988-01-10')
        ->set('beneficiaryBloodType', 'b_positive')
        ->set('beneficiaryPhoto', UploadedFile::fake()->image('blocked-brother.jpg'))
        ->call('saveBeneficiary')
        ->assertHasErrors(['beneficiaryRelationship'])
        ->assertCount('beneficiaries', 0);
});
