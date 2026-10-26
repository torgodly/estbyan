<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\CardDeliveryRecipient;
use App\Filament\Pages\DeliveredInsuranceCardsReport;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\DeliveredEmployeesExport;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('exports delivered families to excel from the report page', function () {
    $courier = User::factory()->cardDelivery()->create([
        'name' => 'المنطقة الغربية',
    ]);
    $admin = User::factory()->smartCare()->create();

    $registration = MedicalRegistration::factory()->submitted()->create();
    $registration->employee->update([
        'full_name' => 'عائلة مُسلَّمة للتصدير',
        'workplace' => 'sebha',
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $registration->employee->markCardsDelivered($courier, CardDeliveryRecipient::Administration);

    $this->actingAs($admin);

    Livewire::test(DeliveredInsuranceCardsReport::class)
        ->assertActionVisible('exportExcel')
        ->callAction('exportExcel')
        ->assertFileDownloaded(DeliveredEmployeesExport::FILENAME);

    $binary = DeliveredEmployeesExport::binary(
        Employee::query()->cardsDelivered(),
    );
    $temp = tempnam(sys_get_temp_dir(), 'delivered-export-');
    file_put_contents($temp, $binary);

    $sheet = IOFactory::load($temp)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('الاسم')
        ->and($sheet->getCell('A2')->getValue())->toBe('عائلة مُسلَّمة للتصدير')
        ->and($sheet->getCell('G2')->getValue())->toBe('2')
        ->and($sheet->getCell('H2')->getValue())->toBe('الإدارة')
        ->and($sheet->getCell('I2')->getValue())->toBe('المنطقة الغربية');

    @unlink($temp);
});
