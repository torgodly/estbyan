<?php

use App\Enums\BeneficiaryCancellationReason;
use App\Enums\BeneficiaryRelationship;
use App\Enums\CardDeliveryRecipient;
use App\Enums\RegistrationStatus;
use App\Enums\ReviewFlagReason;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use App\Models\User;
use App\Support\FullyPrintedEmployeesExport;
use Illuminate\Testing\PendingCommand;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * @param  list<array{0: string|int, 1?: string}>  $rows  [employee number, name]
 */
function deliveryNumbersFile(array $rows, bool $numbersAsText = true): string
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'الرقم الوظيفي');
    $sheet->setCellValue('B1', 'الاسم');

    foreach ($rows as $index => $row) {
        $numbersAsText
            ? $sheet->setCellValueExplicit('A'.($index + 2), (string) $row[0], DataType::TYPE_STRING)
            : $sheet->setCellValue('A'.($index + 2), (int) $row[0]);
        $sheet->setCellValue('B'.($index + 2), $row[1] ?? '');
    }

    $path = tempnam(sys_get_temp_dir(), 'delivery').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function fullyPrintedFamily(string $employeeNumber, string $name, bool $printFamily = true): MedicalRegistration
{
    $employee = Employee::factory()->create(['employee_number' => $employeeNumber, 'full_name' => $name]);
    $registration = MedicalRegistration::factory()->approved()->create(['employee_id' => $employee->id]);
    $employee->markCardPrinted();

    $son = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Son,
    ]);

    if ($printFamily) {
        $son->markCardPrinted();
    }

    return $registration;
}

function deliverFromList(string $file, array $options = []): PendingCommand
{
    return test()->artisan('cards:deliver-from-list', [
        'file' => $file,
        '--to' => 'administration',
        '--by' => User::query()->firstOrFail()->email,
        '--force' => true,
        ...$options,
    ]);
}

beforeEach(fn () => User::factory()->smartCare()->create());

it('marks the families in the fully printed export as delivered', function () {
    $first = fullyPrintedFamily('001433', 'محبة نجيب احمد شنيب');
    $second = fullyPrintedFamily('002521', 'عائشة فرج عبدالعزيز الورفلي');

    $file = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
    file_put_contents($file, FullyPrintedEmployeesExport::binary(Employee::query()->cardsFullyPrinted()));

    deliverFromList($file)
        ->expectsOutputToContain('Delivered 2 famil(ies).')
        ->assertSuccessful();

    $employee = $first->employee->fresh();

    expect($employee->cardsAreDelivered())->toBeTrue()
        ->and($employee->cards_delivered_to)->toBe(CardDeliveryRecipient::Administration)
        ->and($employee->cards_delivered_by)->toBe(User::query()->first()->id)
        ->and($second->employee->fresh()->cardsAreDelivered())->toBeTrue();
});

it('only matches the exact employee number and explains lost leading zeros', function () {
    $registration = fullyPrintedFamily('001433', 'محبة نجيب احمد شنيب');

    deliverFromList(deliveryNumbersFile([['1433']], numbersAsText: false))
        ->expectsOutputToContain('ربما حُذفت الأصفار')
        ->expectsOutputToContain('Ready to mark 0 famil(ies).')
        ->assertSuccessful();

    expect($registration->employee->fresh()->cardsAreDelivered())->toBeFalse();
});

it('refuses a row whose name does not match the employee', function () {
    $registration = fullyPrintedFamily('001433', 'محبة نجيب احمد شنيب');

    deliverFromList(deliveryNumbersFile([['001433', 'مودة نجيب احمد شنيب']]))
        ->expectsOutputToContain('لا يطابق اسم الموظف')
        ->assertSuccessful();

    expect($registration->employee->fresh()->cardsAreDelivered())->toBeFalse();
});

it('accepts the same name with spelling differences', function () {
    $registration = fullyPrintedFamily('001433', 'محبة نجيب أحمد شنيب');

    deliverFromList(deliveryNumbersFile([['001433', 'محبه نجيب احمد  شنيب']]))
        ->expectsOutputToContain('Delivered 1 famil(ies).')
        ->assertSuccessful();

    expect($registration->employee->fresh()->cardsAreDelivered())->toBeTrue();
});

it('does not mark inactive employees or families with unprinted cards, unapproved registrations or open reviews', function () {
    $unprinted = fullyPrintedFamily('000001', 'موظف أول', printFamily: false);
    $declined = fullyPrintedFamily('000002', 'موظف ثاني');
    $declined->update(['status' => RegistrationStatus::Declined]);
    $flagged = fullyPrintedFamily('000003', 'موظف ثالث');
    ReviewFlag::raise($flagged->beneficiaries()->first(), ReviewFlagReason::PossibleDuplicateFamilyMember, 'x');

    $inactive = fullyPrintedFamily('000004', 'موظف رابع');
    $inactive->employee->update(['is_active' => false]);

    deliverFromList(deliveryNumbersFile([['000001'], ['000002'], ['000003'], ['000004']]))
        ->expectsOutputToContain('بطاقات غير مطبوعة')
        ->expectsOutputToContain('حالة الطلب')
        ->expectsOutputToContain('المراجعة اليدوية')
        ->expectsOutputToContain('الموظف غير نشط')
        ->expectsOutputToContain('Blocked: 4.')
        ->assertSuccessful();

    expect(Employee::query()->cardsDelivered()->count())->toBe(0);
});

it('ignores cancelled family members when checking that every card is printed', function () {
    $registration = fullyPrintedFamily('001433', 'محبة نجيب احمد شنيب');
    Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ])->cancel(BeneficiaryCancellationReason::EmployeeDuplicate);

    deliverFromList(deliveryNumbersFile([['001433']]))
        ->expectsOutputToContain('Delivered 1 famil(ies).')
        ->assertSuccessful();
});

it('counts repeated numbers once and leaves already delivered families as they are', function () {
    $registration = fullyPrintedFamily('001433', 'محبة نجيب احمد شنيب');
    $registration->employee->markCardsDelivered(User::query()->first(), CardDeliveryRecipient::Employee);

    deliverFromList(deliveryNumbersFile([['001433'], ['001433'], ['999999']]))
        ->expectsOutputToContain('Already delivered: 1. Blocked: 0. Not found: 1.')
        ->expectsOutputToContain('Repeated in the file')
        ->assertSuccessful();

    expect($registration->employee->fresh()->cards_delivered_to)->toBe(CardDeliveryRecipient::Employee);
});

it('changes nothing in dry-run mode or when not confirmed', function () {
    $registration = fullyPrintedFamily('001433', 'محبة نجيب احمد شنيب');
    $file = deliveryNumbersFile([['001433']]);

    deliverFromList($file, ['--dry-run' => true, '--force' => false])
        ->expectsOutputToContain('Ready to mark 1 famil(ies).')
        ->assertSuccessful();

    deliverFromList($file, ['--force' => false])
        ->expectsConfirmation('Mark 1 famil(ies) as delivered to الإدارة, recorded by '.User::query()->first()->name.'?', 'no')
        ->assertSuccessful();

    expect($registration->employee->fresh()->cardsAreDelivered())->toBeFalse();
});

it('rejects an unknown recipient or user and a file without the needed column', function () {
    $registration = fullyPrintedFamily('001433', 'محبة نجيب احمد شنيب');
    $file = deliveryNumbersFile([['001433']]);

    $this->artisan('cards:deliver-from-list', ['file' => $file, '--to' => 'someone'])->assertFailed();
    $this->artisan('cards:deliver-from-list', ['file' => $file, '--by' => 'missing@example.com', '--force' => true])->assertFailed();

    expect($registration->employee->fresh()->cardsAreDelivered())->toBeFalse();

    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([['العدد'], ['1']]);
    $noColumn = tempnam(sys_get_temp_dir(), 'delivery').'.xlsx';
    (new Xlsx($spreadsheet))->save($noColumn);

    deliverFromList($noColumn)->assertFailed();
});

/**
 * @param  list<string>  $names
 */
function deliveryNamesFile(array $names): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray([['العدد', 'الاسم'], ...array_map(fn (string $name, int $index): array => [$index + 1, $name], $names, array_keys($names))]);

    $path = tempnam(sys_get_temp_dir(), 'delivery').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

it('marks a name-only list when each name matches exactly one employee', function () {
    $exact = fullyPrintedFamily('001433', 'أروى حسن عبدالله ابوقصيصة');
    $withNote = fullyPrintedFamily('001434', 'عبدالسلام عبدالصمد على ابوشيحة');

    deliverFromList(deliveryNamesFile(['اروى حسن عبدالله ابوقصيصة', 'عبدالسلام عبدالصمد على ابوشيحة/ مكتب زلة', 'أروى حسن عبدالله ابوقصيصة']))
        ->expectsOutputToContain('matching by exact, unique name only')
        ->expectsOutputToContain('Delivered 2 famil(ies).')
        ->assertSuccessful();

    expect($exact->employee->fresh()->cardsAreDelivered())->toBeTrue()
        ->and($withNote->employee->fresh()->cardsAreDelivered())->toBeTrue();
});

it('never guesses a name with a typo or a name shared by two employees', function () {
    $typo = fullyPrintedFamily('005073', 'قدري حسين على القلفاط');
    $twinA = fullyPrintedFamily('000010', 'محمد علي سالم');
    $twinB = fullyPrintedFamily('000011', 'محمد علي سالم');

    deliverFromList(deliveryNamesFile(['قدري حسين علي القلقاط', 'محمد علي سالم', 'ميلود عبدالسلام اسمير']))
        ->expectsOutputToContain('Ready to mark 0 famil(ies). Already delivered: 0. Blocked: 0. Not found: 0. Uncertain names: 3.')
        ->assertSuccessful();

    expect(Employee::query()->cardsDelivered()->count())->toBe(0);
});

it('only needs the file name: delivers to the administration and asks who records it', function () {
    $registration = fullyPrintedFamily('001433', 'محبة نجيب احمد شنيب');
    $user = User::query()->firstOrFail();

    $this->artisan('cards:deliver-from-list', ['file' => deliveryNumbersFile([['001433']])])
        ->expectsChoice('Who is recording this delivery?', $user->email, User::query()->pluck('email')->all())
        ->expectsConfirmation('Mark 1 famil(ies) as delivered to الإدارة, recorded by '.$user->name.'?', 'yes')
        ->expectsOutputToContain('Delivered 1 famil(ies).')
        ->assertSuccessful();

    $employee = $registration->employee->fresh();

    expect($employee->cards_delivered_to)->toBe(CardDeliveryRecipient::Administration)
        ->and($employee->cards_delivered_by)->toBe($user->id);
});

it('finds a list by its name inside the delivery lists folder', function () {
    $this->artisan('cards:deliver-from-list', ['file' => 'tafeel-2026-10.xlsx', '--dry-run' => true])
        ->expectsOutputToContain('matching by exact, unique name only')
        ->assertSuccessful();
});
