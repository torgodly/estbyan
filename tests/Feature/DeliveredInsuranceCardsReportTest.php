<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\CardDeliveryRecipient;
use App\Filament\Pages\DeliveredInsuranceCardsReport;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

it('hides the delivered cards report from reviewers and delivery accounts', function () {
    $reviewer = User::factory()->reviewer()->create();
    $delivery = User::factory()->cardDelivery()->create();

    $this->actingAs($reviewer);
    Livewire::test(DeliveredInsuranceCardsReport::class)->assertForbidden();

    $this->actingAs($delivery);
    Livewire::test(DeliveredInsuranceCardsReport::class)->assertForbidden();
});

it('lets hr and smart care open the delivered cards report', function () {
    $hr = User::factory()->hr()->create();
    $support = User::factory()->smartCare()->create();

    $this->actingAs($hr);
    Livewire::test(DeliveredInsuranceCardsReport::class)
        ->assertSuccessful()
        ->assertSee('تقرير العائلات المُسلَّمة');

    $this->actingAs($support);
    Livewire::test(DeliveredInsuranceCardsReport::class)
        ->assertSuccessful()
        ->assertSee('عائلات مُسلَّمة');
});

it('lists only delivered families with recipient, courier, and card counts', function () {
    $east = User::factory()->cardDelivery()->create([
        'name' => 'المنطقة الشرقية',
    ]);
    $west = User::factory()->cardDelivery()->create([
        'name' => 'المنطقة الغربية',
    ]);
    $admin = User::factory()->smartCare()->create();

    $delivered = MedicalRegistration::factory()->submitted()->create();
    $delivered->employee->update(['full_name' => 'خالد المُسلَّم']);
    Beneficiary::factory()->create([
        'medical_registration_id' => $delivered->id,
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $delivered->id,
        'relationship' => BeneficiaryRelationship::Father,
    ]);
    $delivered->employee->markCardsDelivered($east, CardDeliveryRecipient::Employee);

    $otherDelivered = Employee::factory()->create([
        'full_name' => 'منى الإدارية',
        'workplace' => 'tripoli',
    ]);
    $otherDelivered->markCardsDelivered($west, CardDeliveryRecipient::Administration);

    Employee::factory()->create([
        'full_name' => 'لم يُسلَّم بعد',
    ]);

    $this->actingAs($admin);

    Livewire::test(DeliveredInsuranceCardsReport::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$delivered->employee, $otherDelivered])
        ->assertCanNotSeeTableRecords([Employee::query()->where('full_name', 'لم يُسلَّم بعد')->first()])
        ->assertSee('خالد المُسلَّم')
        ->assertSee('المنطقة الشرقية')
        ->assertSee('الموظف')
        ->assertSee('منى الإدارية')
        ->assertSee('المنطقة الغربية')
        ->assertSee('الإدارة')
        ->filterTable('cards_delivered_to', CardDeliveryRecipient::Employee->value)
        ->assertCanSeeTableRecords([$delivered->employee])
        ->assertCanNotSeeTableRecords([$otherDelivered])
        ->filterTable('cards_delivered_to', CardDeliveryRecipient::Administration->value)
        ->assertCanSeeTableRecords([$otherDelivered])
        ->assertCanNotSeeTableRecords([$delivered->employee])
        ->filterTable('cards_delivered_by', (string) $west->id)
        ->assertCanSeeTableRecords([$otherDelivered])
        ->assertCanNotSeeTableRecords([$delivered->employee]);
});

it('filters delivered families by workplace', function () {
    $admin = User::factory()->hr()->create();
    $courier = User::factory()->cardDelivery()->create();

    $tripoli = Employee::factory()->create([
        'full_name' => 'موظف طرابلس',
        'workplace' => 'tripoli',
    ]);
    $tripoli->markCardsDelivered($courier, CardDeliveryRecipient::Employee);

    $sebha = Employee::factory()->create([
        'full_name' => 'موظف سبها',
        'workplace' => 'sebha',
    ]);
    $sebha->markCardsDelivered($courier, CardDeliveryRecipient::Administration);

    $this->actingAs($admin);

    Livewire::test(DeliveredInsuranceCardsReport::class)
        ->filterTable('workplace', 'tripoli')
        ->assertCanSeeTableRecords([$tripoli])
        ->assertCanNotSeeTableRecords([$sebha]);
});
