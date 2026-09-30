<?php

use App\Enums\BeneficiaryRelationship;
use App\Filament\Pages\ExportPrintedCards;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\PrintedEmployeesPeriodExport;
use App\Support\PrintedEmployeesPeriodPdf;
use Carbon\Carbon;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('hides the printed cards export from hr and reviewers', function () {
    $hr = User::factory()->hr()->create();
    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($hr);
    Livewire::test(ExportPrintedCards::class)->assertForbidden();

    $this->actingAs($reviewer);
    Livewire::test(ExportPrintedCards::class)->assertForbidden();
});

it('lets support users open the printed cards export', function () {
    $support = User::factory()->smartCare()->create();

    $this->actingAs($support);

    Livewire::test(ExportPrintedCards::class)
        ->assertSuccessful()
        ->assertSee('تصدير البطاقات المطبوعة')
        ->assertSee('من تاريخ')
        ->assertSee('إلى تاريخ')
        ->assertSee('تصدير Excel')
        ->assertSee('تحميل PDF');
});

it('exports only employees printed in the selected period', function () {
    Employee::factory()->create([
        'full_name' => 'أحمد المطبوع اليوم',
        'workplace' => 'tripoli',
        'office' => 'مكتب التحصيل',
        'card_printed_at' => Carbon::parse('2026-09-24 10:00:00', PrintedEmployeesPeriodExport::TIMEZONE),
    ]);
    Employee::factory()->create([
        'full_name' => 'سارة خارج الفترة',
        'workplace' => 'sebha',
        'card_printed_at' => Carbon::parse('2026-09-20 10:00:00', PrintedEmployeesPeriodExport::TIMEZONE),
    ]);
    Employee::factory()->create([
        'full_name' => 'سالم غير مطبوع',
        'workplace' => 'misrata',
        'card_printed_at' => null,
    ]);

    $rows = PrintedEmployeesPeriodExport::rows('2026-09-24', '2026-09-24');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('أحمد المطبوع اليوم')
        ->and($rows[0]['workplace'])->toBe('طرابلس')
        ->and($rows[0]['office'])->toBe('مكتب التحصيل')
        ->and($rows[0]['status'])->toBe('تمت الطباعه');
});

it('hides a printed employee when any family member is not printed', function () {
    $printedAt = Carbon::parse('2026-09-24 10:00:00', PrintedEmployeesPeriodExport::TIMEZONE);

    $complete = Employee::factory()->create([
        'full_name' => 'كامل الطباعة',
        'card_printed_at' => $printedAt,
    ]);
    $completeRegistration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $complete->id,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $completeRegistration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
        'card_printed_at' => $printedAt,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $completeRegistration->id,
        'relationship' => BeneficiaryRelationship::Mother,
        'card_printed_at' => $printedAt,
    ]);

    $incomplete = Employee::factory()->create([
        'full_name' => 'ناقص ابن',
        'card_printed_at' => $printedAt,
    ]);
    $incompleteRegistration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $incomplete->id,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $incompleteRegistration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
        'card_printed_at' => $printedAt,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $incompleteRegistration->id,
        'relationship' => BeneficiaryRelationship::Son,
        'card_printed_at' => null,
    ]);

    $rows = PrintedEmployeesPeriodExport::rows('2026-09-24', '2026-09-24');

    expect(collect($rows)->pluck('name')->all())->toBe(['كامل الطباعة']);

    $slips = PrintedEmployeesPeriodExport::slips('2026-09-24', '2026-09-24');

    expect($slips)->toHaveCount(1)
        ->and($slips[0]['name'])->toBe('كامل الطباعة')
        ->and($slips[0]['cards'])->toBe(3);
});

it('builds an arabic excel workbook for the selected period', function () {
    Employee::factory()->create([
        'full_name' => 'نورة العابد',
        'workplace' => 'general_admin',
        'office' => 'مكتب نائب المدير العام',
        'card_printed_at' => Carbon::parse('2026-09-24 15:30:00', PrintedEmployeesPeriodExport::TIMEZONE),
    ]);

    $binary = PrintedEmployeesPeriodExport::binary('2026-09-24', '2026-09-24');
    $temporary = tempnam(sys_get_temp_dir(), 'printed-period-');
    file_put_contents($temporary, $binary);

    $sheet = IOFactory::load($temporary)->getActiveSheet();

    expect($sheet->getCell('A1')->getValue())->toBe('الاسم')
        ->and($sheet->getCell('B1')->getValue())->toBe('الإدارة')
        ->and($sheet->getCell('C1')->getValue())->toBe('المكتب')
        ->and($sheet->getCell('D1')->getValue())->toBe('الحالة')
        ->and($sheet->getCell('A2')->getValue())->toBe('نورة العابد')
        ->and($sheet->getCell('B2')->getValue())->toBe('الإدارة العامة')
        ->and($sheet->getCell('C2')->getValue())->toBe('مكتب نائب المدير العام')
        ->and($sheet->getCell('D2')->getValue())->toBe('تمت الطباعه')
        ->and($sheet->getCell('A3')->getValue())->toBeNull();

    unlink($temporary);
});

it('lets support export the selected period from the page', function () {
    $support = User::factory()->smartCare()->create();

    Employee::factory()->create([
        'full_name' => 'موظف للتصدير',
        'workplace' => 'tripoli',
        'card_printed_at' => Carbon::parse('2026-09-24 09:00:00', PrintedEmployeesPeriodExport::TIMEZONE),
    ]);

    $this->actingAs($support);

    Livewire::test(ExportPrintedCards::class)
        ->fillForm([
            'printed_from' => '2026-09-24',
            'printed_until' => '2026-09-24',
        ])
        ->call('export')
        ->assertHasNoFormErrors()
        ->assertFileDownloaded();
});

it('builds an a4 pdf with four slips per page including the employee card', function () {
    $printedAt = Carbon::parse('2026-09-24 09:00:00', PrintedEmployeesPeriodExport::TIMEZONE);

    foreach (range(1, 5) as $index) {
        Employee::factory()->create([
            'full_name' => "موظف رقم {$index}",
            'workplace' => 'tripoli',
            'card_printed_at' => $printedAt,
        ]);
    }

    $binary = PrintedEmployeesPeriodPdf::binary('2026-09-24', '2026-09-24');

    expect($binary)->toStartWith('%PDF-1.4')
        ->and(substr_count($binary, '/Type /Page /Parent'))->toBe(2)
        ->and($binary)->toContain('/MediaBox [0 0 842 595]');
});

it('lets support export the selected period as pdf', function () {
    $support = User::factory()->smartCare()->create();

    Employee::factory()->create([
        'full_name' => 'موظف للـ PDF',
        'workplace' => 'tripoli',
        'card_printed_at' => Carbon::parse('2026-09-24 09:00:00', PrintedEmployeesPeriodExport::TIMEZONE),
    ]);

    $this->actingAs($support);

    Livewire::test(ExportPrintedCards::class)
        ->fillForm([
            'printed_from' => '2026-09-24',
            'printed_until' => '2026-09-24',
        ])
        ->call('exportPdf')
        ->assertHasNoFormErrors()
        ->assertFileDownloaded();
});
