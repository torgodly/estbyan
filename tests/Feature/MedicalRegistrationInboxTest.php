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

it('finds a request by family member name', function () {
    $admin = User::factory()->create();

    $familyMatch = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'حليمة مصطفى محمد الاسطى',
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $familyMatch->id,
        'full_name' => 'عمر نور الدين عمر',
    ]);
    $other = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف آخر للأسماء',
    ]);
    Beneficiary::factory()->create([
        'medical_registration_id' => $other->id,
        'full_name' => 'سارة علي',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->set('activeTab', 'all')
        ->searchTable('عمر نور الدين')
        ->assertCanSeeTableRecords([$familyMatch])
        ->assertCanNotSeeTableRecords([$other]);
});

it('finds a request by phone or whatsapp number', function () {
    $admin = User::factory()->create();

    $phoneMatch = MedicalRegistration::factory()->submitted()->create([
        'phone' => '0912345678',
        'whatsapp' => '0921111111',
    ]);
    $whatsappMatch = MedicalRegistration::factory()->submitted()->create([
        'phone' => '0944444444',
        'whatsapp' => '0918765432',
    ]);
    $other = MedicalRegistration::factory()->submitted()->create([
        'phone' => '0955555555',
        'whatsapp' => '0966666666',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->set('activeTab', 'all')
        ->searchTable('0912345678')
        ->assertCanSeeTableRecords([$phoneMatch])
        ->assertCanNotSeeTableRecords([$whatsappMatch, $other])
        ->searchTable('091-876-5432')
        ->assertCanSeeTableRecords([$whatsappMatch])
        ->assertCanNotSeeTableRecords([$phoneMatch, $other]);
});

it('finds a request by employee or family member card number', function () {
    $admin = User::factory()->create();

    $employeeMatch = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف ببطاقة',
    ]);
    $employeeMatch->employee->forceFill(['card_number' => '23456789'])->saveQuietly();

    $familyMatch = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف لعائلة ببطاقة',
    ]);
    $familyMatch->employee->forceFill(['card_number' => '34567890'])->saveQuietly();
    Beneficiary::factory()->create([
        'medical_registration_id' => $familyMatch->id,
        'full_name' => 'فرد العائلة',
        'card_number' => '45678901',
    ]);

    $other = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف آخر ببطاقة',
    ]);
    $other->employee->forceFill(['card_number' => '56789012'])->saveQuietly();

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->set('activeTab', 'all')
        ->searchTable('SC-23456789')
        ->assertCanSeeTableRecords([$employeeMatch])
        ->assertCanNotSeeTableRecords([$familyMatch, $other])
        ->searchTable('45678901')
        ->assertCanSeeTableRecords([$familyMatch])
        ->assertCanNotSeeTableRecords([$employeeMatch, $other]);
});
