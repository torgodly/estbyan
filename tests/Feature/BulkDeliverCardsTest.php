<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\CardDeliveryRecipient;
use App\Filament\Pages\BulkDeliverCards;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

function bulkDeliveryFamily(string $employeeNumber, string $name, bool $printFamily = true): Employee
{
    $employee = Employee::factory()->create(['employee_number' => $employeeNumber, 'full_name' => $name]);
    $registration = MedicalRegistration::factory()->approved()->create(['employee_id' => $employee->id]);
    $employee->markCardPrinted();

    $child = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Son,
    ]);

    if ($printFamily) {
        $child->markCardPrinted();
    }

    return $employee;
}

it('previews the pasted employee numbers and delivers only the ready families', function () {
    $user = User::factory()->hr()->create();
    $ready = bulkDeliveryFamily('001433', 'محبة نجيب احمد شنيب');
    $blocked = bulkDeliveryFamily('017024', 'حاتم على عبدالسلام سلحب', printFamily: false);

    $this->actingAs($user);

    Livewire::test(BulkDeliverCards::class)
        ->fillForm(['employee_numbers' => "001433\n017024, 999999\n001433"])
        ->call('previewList')
        ->assertSee('جاهز للتسليم')
        ->assertSee('محبة نجيب احمد شنيب')
        ->assertSee('بطاقات غير مطبوعة')
        ->assertSee('لا يوجد موظف بهذا الرقم')
        ->assertSee('مكرر في القائمة')
        ->assertActionVisible('deliverAll')
        ->callAction('deliverAll')
        ->assertNotified('تم تسليم 1 عائلة')
        ->assertSee('تم التسليم')
        ->assertActionHidden('deliverAll');

    expect($ready->fresh()->cardsAreDelivered())->toBeTrue()
        ->and($ready->fresh()->cards_delivered_to)->toBe(CardDeliveryRecipient::Administration)
        ->and($ready->fresh()->cards_delivered_by)->toBe($user->id)
        ->and($blocked->fresh()->cardsAreDelivered())->toBeFalse();
});

it('records the chosen recipient', function () {
    $this->actingAs(User::factory()->smartCare()->create());
    $employee = bulkDeliveryFamily('001433', 'محبة نجيب احمد شنيب');

    Livewire::test(BulkDeliverCards::class)
        ->fillForm(['employee_numbers' => '001433', 'delivered_to' => CardDeliveryRecipient::Employee->value])
        ->call('previewList')
        ->callAction('deliverAll');

    expect($employee->fresh()->cards_delivered_to)->toBe(CardDeliveryRecipient::Employee);
});

it('keeps leading zeros and hides the deliver button until something is ready', function () {
    $this->actingAs(User::factory()->hr()->create());
    bulkDeliveryFamily('001433', 'محبة نجيب احمد شنيب');

    Livewire::test(BulkDeliverCards::class)
        ->assertActionHidden('deliverAll')
        ->fillForm(['employee_numbers' => '1433'])
        ->call('previewList')
        ->assertSee('ربما حُذفت الأصفار')
        ->assertActionHidden('deliverAll');

    expect(BulkDeliverCards::parseNumbers("001433\n 002521,003028 ، 004047"))->toBe(['001433', '002521', '003028', '004047']);
});

it('delivers exactly the previewed list, not text typed afterwards', function () {
    $this->actingAs(User::factory()->hr()->create());
    $previewed = bulkDeliveryFamily('001433', 'محبة نجيب احمد شنيب');

    Livewire::test(BulkDeliverCards::class)
        ->fillForm(['employee_numbers' => '001433'])
        ->call('previewList')
        ->set('data.employee_numbers', '002521')
        ->assertSet('preview', null)
        ->assertActionHidden('deliverAll');

    expect($previewed->fresh()->cardsAreDelivered())->toBeFalse();
});

it('is only available to HR and Smart Care', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    Livewire::test(BulkDeliverCards::class)->assertForbidden();
})->with(['reviewer', 'cardDelivery']);
