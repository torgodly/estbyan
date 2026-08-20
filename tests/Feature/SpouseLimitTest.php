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

it('shows wife label for male employees and husband label for female employees', function () {
    $maleNationalId = LibyanNationalId::generate(Gender::Male, 1980);

    Employee::factory()->create([
        'employee_number' => '9101',
        'national_id' => $maleNationalId,
        'full_name' => 'أحمد المتزوج',
        'workplace' => 'tripoli',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $maleNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->assertSee('زوجة')
        ->assertDontSeeHtml('>زوج / زوجة</option>');

    $femaleNationalId = LibyanNationalId::generate(Gender::Female, 1981);

    Employee::factory()->create([
        'employee_number' => '9102',
        'national_id' => $femaleNationalId,
        'full_name' => 'فاطمة المتزوجة',
        'workplace' => 'benghazi',
    ]);

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $femaleNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('step', 4)
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->assertSeeHtml('>زوج</option>')
        ->assertDontSeeHtml('>زوجة</option>');
});

it('allows a male employee to add up to four wives then blocks the fifth', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1975);

    Employee::factory()->create([
        'employee_number' => '9103',
        'national_id' => $employeeNationalId,
        'full_name' => 'عمر متعدد الزوجات',
        'workplace' => 'misrata',
    ]);

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married');

    foreach (range(1, 4) as $index) {
        $wifeNationalId = LibyanNationalId::generate(Gender::Female, 1980 + $index);
        $photo = UploadedFile::fake()->image("wife-{$index}.jpg");

        $component
            ->set('showBeneficiaryForm', true)
            ->set('beneficiaryName', "زوجة رقم {$index}")
            ->set('beneficiaryRelationship', 'spouse')
            ->set('beneficiaryIsLibyan', true)
            ->set('beneficiaryNationalId', $wifeNationalId)
            ->set('beneficiaryDateOfBirth', (1980 + $index).'-05-10')
            ->set('beneficiaryBloodType', 'a_positive')
            ->set('beneficiaryPhoto', $photo)
            ->call('saveBeneficiary')
            ->assertHasNoErrors();
    }

    expect($component->get('beneficiaries'))->toHaveCount(4);

    $fifthNationalId = LibyanNationalId::generate(Gender::Female, 1990);
    $photo = UploadedFile::fake()->image('wife-5.jpg');

    $component
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'زوجة خامسة')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', true)
        ->set('beneficiaryNationalId', $fifthNationalId)
        ->set('beneficiaryDateOfBirth', '1990-05-10')
        ->set('beneficiaryBloodType', 'a_positive')
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasErrors(['beneficiaryRelationship']);

    expect($component->get('beneficiaries'))->toHaveCount(4);
});

it('allows a female employee only one husband', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Female, 1982);

    Employee::factory()->create([
        'employee_number' => '9104',
        'national_id' => $employeeNationalId,
        'full_name' => 'سارة الموظفة',
        'workplace' => 'sebha',
    ]);

    $husbandNationalId = LibyanNationalId::generate(Gender::Male, 1978);
    $photo = UploadedFile::fake()->image('husband.jpg');

    $component = Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'الزوج')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', true)
        ->set('beneficiaryNationalId', $husbandNationalId)
        ->set('beneficiaryDateOfBirth', '1978-04-12')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasNoErrors()
        ->assertCount('beneficiaries', 1);

    $secondHusbandNationalId = LibyanNationalId::generate(Gender::Male, 1979);
    $secondPhoto = UploadedFile::fake()->image('husband-2.jpg');

    $component
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'زوج ثاني')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', true)
        ->set('beneficiaryNationalId', $secondHusbandNationalId)
        ->set('beneficiaryDateOfBirth', '1979-04-12')
        ->set('beneficiaryBloodType', 'o_positive')
        ->set('beneficiaryPhoto', $secondPhoto)
        ->call('saveBeneficiary')
        ->assertHasErrors(['beneficiaryRelationship']);

    expect($component->get('beneficiaries'))->toHaveCount(1);
});

it('requires the spouse national id gender to match the opposite of the employee', function () {
    $employeeNationalId = LibyanNationalId::generate(Gender::Male, 1970);
    $wrongSpouseNationalId = LibyanNationalId::generate(Gender::Male, 1985);

    Employee::factory()->create([
        'employee_number' => '9105',
        'national_id' => $employeeNationalId,
        'full_name' => 'خالد',
        'workplace' => 'tripoli',
    ]);

    $photo = UploadedFile::fake()->image('wrong-spouse.jpg');

    Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $employeeNationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('maritalStatus', 'married')
        ->set('showBeneficiaryForm', true)
        ->set('beneficiaryName', 'اسم خاطئ')
        ->set('beneficiaryRelationship', 'spouse')
        ->set('beneficiaryIsLibyan', true)
        ->set('beneficiaryNationalId', $wrongSpouseNationalId)
        ->set('beneficiaryDateOfBirth', '1985-06-01')
        ->set('beneficiaryBloodType', 'b_positive')
        ->set('beneficiaryPhoto', $photo)
        ->call('saveBeneficiary')
        ->assertHasErrors(['beneficiaryNationalId']);
});
