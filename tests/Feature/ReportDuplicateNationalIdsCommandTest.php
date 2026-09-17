<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\LibyanNationalId;
use Illuminate\Support\Facades\Artisan;

it('reports employee national ids that appear as another employees beneficiary', function () {
    $husband = Employee::factory()->create([
        'full_name' => 'الزوج الموظف',
        'employee_number' => '9301',
    ]);
    $wife = Employee::factory()->create([
        'full_name' => 'الزوجة الموظفة',
        'employee_number' => '9302',
    ]);

    $husbandRegistration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $husband->id,
        'employee_number' => $husband->employee_number,
        'national_id' => $husband->national_id,
        'full_name' => $husband->full_name,
    ]);
    $wifeRegistration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $wife->id,
        'employee_number' => $wife->employee_number,
        'national_id' => $wife->national_id,
        'full_name' => $wife->full_name,
    ]);

    Beneficiary::factory()->create([
        'medical_registration_id' => $husbandRegistration->id,
        'full_name' => $wife->full_name,
        'relationship' => BeneficiaryRelationship::Spouse,
        'national_id' => $wife->national_id,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $wifeRegistration->id,
        'full_name' => $husband->full_name,
        'relationship' => BeneficiaryRelationship::Spouse,
        'national_id' => $husband->national_id,
    ]);

    Artisan::call('employees:report-duplicate-nids');
    $output = Artisan::output();

    expect($output)
        ->toContain('Employee NIDs added as someone else\'s beneficiary')
        ->toContain($husband->national_id)
        ->toContain($wife->national_id)
        ->toContain('الزوج الموظف')
        ->toContain('الزوجة الموظفة')
        ->not->toContain('No duplicated national IDs found.');
});

it('reports a beneficiary national id used on more than one registration', function () {
    $parentNationalId = LibyanNationalId::generate(Gender::Male, 1955);

    $first = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'الابن الأول',
        'employee_number' => '9303',
    ]);
    $second = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'الابن الثاني',
        'employee_number' => '9304',
    ]);

    Beneficiary::factory()->create([
        'medical_registration_id' => $first->id,
        'full_name' => 'الأب المشترك',
        'relationship' => BeneficiaryRelationship::Father,
        'national_id' => $parentNationalId,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $second->id,
        'full_name' => 'الأب المشترك',
        'relationship' => BeneficiaryRelationship::Father,
        'national_id' => $parentNationalId,
    ]);

    Artisan::call('employees:report-duplicate-nids');
    $output = Artisan::output();

    expect($output)
        ->toContain('Beneficiary NIDs used on more than one registration')
        ->toContain($parentNationalId)
        ->toContain('الابن الأول')
        ->toContain('الابن الثاني');
});

it('reports nothing when national ids are unique', function () {
    MedicalRegistration::factory()->submitted()->create();

    Artisan::call('employees:report-duplicate-nids');

    expect(Artisan::output())->toContain('No duplicated national IDs found.');
});
