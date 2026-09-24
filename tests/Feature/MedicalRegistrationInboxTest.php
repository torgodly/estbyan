<?php

use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use App\Filament\Resources\MedicalRegistrations\Pages\ListMedicalRegistrations;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

it('does not allow creating or editing registrations from the admin panel', function () {
    $registration = MedicalRegistration::factory()->submitted()->create();

    expect(MedicalRegistrationResource::canCreate())->toBeFalse()
        ->and(MedicalRegistrationResource::canEdit($registration))->toBeFalse()
        ->and(MedicalRegistrationResource::getPages())->not->toHaveKeys(['create', 'edit']);
});

it('defaults to the pending review tab and filters records', function () {
    $admin = User::factory()->create();

    $pending = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف بانتظار',
    ]);
    $approved = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'موظف مقبول',
    ]);
    MedicalRegistration::factory()->create([
        'full_name' => 'موظف مسودة',
        'status' => RegistrationStatus::Draft,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->assertSuccessful()
        ->assertSee('بانتظار المراجعة')
        ->assertSee('موظف بانتظار')
        ->assertDontSee('موظف مقبول')
        ->assertDontSee('موظف مسودة')
        ->set('activeTab', 'approved')
        ->assertSee('موظف مقبول')
        ->assertDontSee('موظف بانتظار')
        ->set('activeTab', 'all')
        ->assertSee('موظف بانتظار')
        ->assertSee('موظف مقبول')
        ->assertSee('موظف مسودة');

    expect($pending->isPendingReview())->toBeTrue()
        ->and($approved->isApproved())->toBeTrue();
});

it('filters submissions by workplace', function () {
    $admin = User::factory()->create();

    MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف طرابلس',
        'workplace' => 'tripoli',
    ]);
    MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف سبها',
        'workplace' => 'sebha',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->assertSee('موظف طرابلس')
        ->assertSee('موظف سبها')
        ->filterTable('workplace', 'tripoli')
        ->assertSee('موظف طرابلس')
        ->assertDontSee('موظف سبها');
});

it('filters requests by insurance card print status', function () {
    $admin = User::factory()->create();

    $printedEmployee = Employee::factory()->create([
        'card_printed_at' => now(),
    ]);
    $unprintedEmployee = Employee::factory()->create([
        'card_printed_at' => null,
    ]);
    $partialEmployee = Employee::factory()->create([
        'card_printed_at' => now(),
    ]);

    $printed = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $printedEmployee->id,
        'full_name' => 'طلب مطبوع بالكامل',
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $printed->id,
        'card_printed_at' => now(),
    ]);

    $unprinted = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $unprintedEmployee->id,
        'full_name' => 'طلب غير مطبوع',
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $unprinted->id,
        'card_printed_at' => null,
    ]);

    $partial = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $partialEmployee->id,
        'full_name' => 'طلب مطبوع جزئيا',
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $partial->id,
        'card_printed_at' => null,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->assertSuccessful()
        ->assertSee('حالة الطباعة')
        ->assertCanSeeTableRecords([$printed, $unprinted, $partial])
        ->filterTable('print_status', 'printed')
        ->assertCanSeeTableRecords([$printed])
        ->assertCanNotSeeTableRecords([$unprinted, $partial])
        ->filterTable('print_status', 'unprinted')
        ->assertCanSeeTableRecords([$unprinted])
        ->assertCanNotSeeTableRecords([$printed, $partial])
        ->filterTable('print_status', 'partial')
        ->assertCanSeeTableRecords([$partial])
        ->assertCanNotSeeTableRecords([$printed, $unprinted]);
});

it('finds a request by employee or family member national id', function () {
    $admin = User::factory()->create();

    $employeeMatch = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف بالرقم الوطني',
        'national_id' => '119890111111',
    ]);
    $familyMatch = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف لابنته',
        'national_id' => '119890222222',
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $familyMatch->id,
        'full_name' => 'ابنة المستفيد',
        'national_id' => '219890263624',
    ]);
    $other = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف آخر للبحث',
        'national_id' => '119890333333',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->set('activeTab', 'all')
        ->searchTable('119890111111')
        ->assertCanSeeTableRecords([$employeeMatch])
        ->assertCanNotSeeTableRecords([$familyMatch, $other])
        ->searchTable('219890263624')
        ->assertCanSeeTableRecords([$familyMatch])
        ->assertCanNotSeeTableRecords([$employeeMatch, $other]);
});
