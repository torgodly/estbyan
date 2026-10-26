<?php

use App\Enums\BeneficiaryRelationship;
use App\Filament\Pages\FullyPrintedInsuranceCardsReport;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\FullyPrintedEmployeesExport;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('hides the fully printed report from reviewers and delivery accounts', function () {
    $reviewer = User::factory()->reviewer()->create();
    $delivery = User::factory()->cardDelivery()->create();

    $this->actingAs($reviewer);
    Livewire::test(FullyPrintedInsuranceCardsReport::class)->assertForbidden();

    $this->actingAs($delivery);
    Livewire::test(FullyPrintedInsuranceCardsReport::class)->assertForbidden();
});

it('lets hr open the fully printed report with excel export', function () {
    $hr = User::factory()->hr()->create();

    $this->actingAs($hr);

    Livewire::test(FullyPrintedInsuranceCardsReport::class)
        ->assertSuccessful()
        ->assertSee('تقرير العائلات المطبوعة بالكامل')
        ->assertActionVisible('exportExcel');
});

it('lists only families where the employee and every beneficiary are printed', function () {
    $admin = User::factory()->smartCare()->create();

    $complete = MedicalRegistration::factory()->submitted()->create();
    $complete->employee->update(['full_name' => 'عائلة مكتملة الطباعة']);
    $complete->employee->markCardPrinted();
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $complete->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $spouse->markCardPrinted();

    $partial = MedicalRegistration::factory()->submitted()->create();
    $partial->employee->update(['full_name' => 'عائلة ناقصة']);
    $partial->employee->markCardPrinted();
    Beneficiary::factory()->create([
        'medical_registration_id' => $partial->id,
        'relationship' => BeneficiaryRelationship::Father,
        'card_printed_at' => null,
    ]);

    $unprinted = MedicalRegistration::factory()->submitted()->create();
    $unprinted->employee->update(['full_name' => 'غير مطبوع']);

    $this->actingAs($admin);

    Livewire::test(FullyPrintedInsuranceCardsReport::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$complete->employee])
        ->assertCanNotSeeTableRecords([$partial->employee, $unprinted->employee])
        ->assertSee('عائلة مكتملة الطباعة')
        ->assertSee('طُبعت بالكامل');
});

it('exports fully printed families to excel', function () {
    $admin = User::factory()->hr()->create();

    $complete = MedicalRegistration::factory()->submitted()->create();
    $complete->employee->update([
        'full_name' => 'موظف للتصدير المطبوع',
        'workplace' => 'tripoli',
    ]);
    $complete->employee->markCardPrinted();
    $child = Beneficiary::factory()->create([
        'medical_registration_id' => $complete->id,
        'relationship' => BeneficiaryRelationship::Son,
    ]);
    $child->markCardPrinted();

    Employee::factory()->create([
        'full_name' => 'غير مكتمل',
        'card_printed_at' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(FullyPrintedInsuranceCardsReport::class)
        ->callAction('exportExcel')
        ->assertFileDownloaded(FullyPrintedEmployeesExport::FILENAME);

    $binary = FullyPrintedEmployeesExport::binary(
        Employee::query()->cardsFullyPrinted(),
    );
    $temp = tempnam(sys_get_temp_dir(), 'printed-export-');
    file_put_contents($temp, $binary);

    $sheet = IOFactory::load($temp)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('الاسم')
        ->and($sheet->getCell('A2')->getValue())->toBe('موظف للتصدير المطبوع')
        ->and($sheet->getCell('G2')->getValue())->toBe('2')
        ->and($sheet->getCell('I2')->getValue())->toBe('طُبعت بالكامل')
        ->and($sheet->getCell('A3')->getValue())->toBeNull();

    @unlink($temp);
});
