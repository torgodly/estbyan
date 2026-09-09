<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\EmployeesFamilyExport;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('groups each employee with their family members and writes arabic excel columns', function () {
    $first = Employee::factory()->create([
        'full_name' => 'أحمد الموظف',
        'national_id' => '119800111111',
    ]);
    $second = Employee::factory()->create([
        'full_name' => 'بكر الموظف',
        'national_id' => '119800222222',
    ]);
    Employee::factory()->create([
        'full_name' => 'زيد بلا عائلة',
        'national_id' => '119800333333',
    ]);

    $firstRegistration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $first->id,
        'full_name' => $first->full_name,
        'national_id' => $first->national_id,
        'gender' => Gender::Male,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $firstRegistration->id,
        'full_name' => 'سالم الابن',
        'relationship' => BeneficiaryRelationship::Son,
        'national_id' => '119900111111',
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $firstRegistration->id,
        'full_name' => 'منى الزوجة',
        'relationship' => BeneficiaryRelationship::Spouse,
        'national_id' => '219800111111',
    ]);

    $secondRegistration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $second->id,
        'full_name' => $second->full_name,
        'national_id' => $second->national_id,
        'gender' => Gender::Female,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $secondRegistration->id,
        'full_name' => 'عمر الزوج',
        'relationship' => BeneficiaryRelationship::Spouse,
        'national_id' => '119700222222',
    ]);

    $draft = MedicalRegistration::factory()->create([
        'employee_id' => $second->id,
        'full_name' => $second->full_name,
        'national_id' => $second->national_id,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $draft->id,
        'full_name' => 'مسودة لا تظهر',
        'relationship' => BeneficiaryRelationship::Mother,
        'national_id' => '219600000000',
    ]);

    $rows = (new EmployeesFamilyExport)->rows();

    expect($rows)->toHaveCount(6)
        ->and(array_column($rows, 'name'))->toBe([
            'أحمد الموظف',
            'منى الزوجة',
            'سالم الابن',
            'بكر الموظف',
            'عمر الزوج',
            'زيد بلا عائلة',
        ])
        ->and($rows[0])->toMatchArray([
            'name' => 'أحمد الموظف',
            'national_id' => '119800111111',
            'type' => 'موظف',
            'relationship' => '—',
            'is_employee' => true,
        ])
        ->and($rows[1])->toMatchArray([
            'name' => 'منى الزوجة',
            'national_id' => '219800111111',
            'type' => 'فرد عائلة',
            'relationship' => 'زوجة',
            'is_employee' => false,
        ])
        ->and($rows[2]['relationship'])->toBe('ابن')
        ->and($rows[4]['relationship'])->toBe('زوج')
        ->and(collect($rows)->pluck('name'))->not->toContain('مسودة لا تظهر');

    $path = tempnam(sys_get_temp_dir(), 'employees-family-').'.xlsx';
    $spreadsheet = (new EmployeesFamilyExport)->spreadsheet();

    try {
        (new Xlsx($spreadsheet))->save($path);

        $sheet = IOFactory::load($path)->getActiveSheet();

        expect($sheet->getTitle())->toBe('الموظفون والعائلة')
            ->and($sheet->getRightToLeft())->toBeTrue()
            ->and($sheet->rangeToArray('A1:D1')[0])->toBe(EmployeesFamilyExport::headings())
            ->and($sheet->getCell('A1')->getValue())->toBe('الاسم')
            ->and($sheet->getCell('B1')->getValue())->toBe('الرقم الوطني')
            ->and($sheet->getCell('A2')->getValue())->toBe('أحمد الموظف')
            ->and($sheet->getCell('B2')->getValue())->toBe('119800111111')
            ->and($sheet->getCell('C2')->getValue())->toBe('موظف')
            ->and($sheet->getCell('D2')->getValue())->toBe('—')
            ->and($sheet->getCell('A3')->getValue())->toBe('منى الزوجة')
            ->and($sheet->getCell('B3')->getValue())->toBe('219800111111')
            ->and($sheet->getCell('C3')->getValue())->toBe('فرد عائلة')
            ->and($sheet->getCell('D3')->getValue())->toBe('زوجة')
            ->and($sheet->getColumnDimension('A')->getWidth())->toBe(36.0)
            ->and($sheet->getColumnDimension('B')->getWidth())->toBe(18.0)
            ->and($sheet->getColumnDimension('C')->getWidth())->toBe(14.0)
            ->and($sheet->getColumnDimension('D')->getWidth())->toBe(16.0)
            ->and($sheet->getColumnDimension('A')->getAutoSize())->toBeFalse();
    } finally {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

it('does not show the family export action on the employees table', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin);

    Livewire::test(ListEmployees::class)
        ->assertSuccessful()
        ->assertDontSee('تصدير الموظفين والعائلة')
        ->assertActionDoesNotExist('exportEmployeesAndFamily');
});
