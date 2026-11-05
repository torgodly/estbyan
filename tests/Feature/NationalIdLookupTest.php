<?php

use App\Enums\BeneficiaryCancellationReason;
use App\Enums\BeneficiaryRelationship;
use App\Enums\Gender;
use App\Filament\Pages\NationalIdLookup;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

/**
 * @return array{employee: Employee, registration: MedicalRegistration, spouse: Beneficiary, son: Beneficiary}
 */
function lookupFamily(): array
{
    $employee = Employee::factory()->create([
        'national_id' => '119900000001',
        'full_name' => 'سالم علي محمد',
    ]);
    $registration = MedicalRegistration::factory()->approved()->create([
        'employee_id' => $employee->id,
        'gender' => Gender::Male,
    ]);
    $spouse = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Spouse,
        'full_name' => 'فاطمة أحمد سالم',
        'national_id' => '219920000002',
    ]);
    $son = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'relationship' => BeneficiaryRelationship::Son,
        'full_name' => 'محمد سالم علي',
        'national_id' => '120150000003',
    ]);

    return [
        'employee' => $employee->refresh(),
        'registration' => $registration,
        'spouse' => $spouse->refresh(),
        'son' => $son->refresh(),
    ];
}

it('is open to every role', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    Livewire::test(NationalIdLookup::class)
        ->assertSuccessful()
        ->assertSee('أدخل الرقم الوطني');
})->with(['hr', 'smartCare', 'reviewer', 'cardDelivery']);

it('shows the employee and family by the employee national id without card numbers', function () {
    ['employee' => $employee, 'spouse' => $spouse, 'son' => $son] = lookupFamily();
    $this->actingAs(User::factory()->cardDelivery()->create());

    expect($employee->card_number)->not->toBeNull()
        ->and($spouse->card_number)->not->toBeNull();

    Livewire::test(NationalIdLookup::class)
        ->set('nationalId', '١١٩٩٠٠٠٠٠٠٠١')
        ->call('search')
        ->assertSet('searchedNationalId', '119900000001')
        ->assertSee('سالم علي محمد')
        ->assertSee($employee->employee_number)
        ->assertSee('فاطمة أحمد سالم')
        ->assertSee('زوجة')
        ->assertSee('محمد سالم علي')
        ->assertSee('ابن')
        ->assertSee('مقبول')
        ->assertDontSee($employee->card_number)
        ->assertDontSee($employee->cardNumberLabel())
        ->assertDontSee($spouse->card_number)
        ->assertDontSee($son->card_number);
});

it('finds the employee family by a family member national id', function () {
    ['son' => $son] = lookupFamily();
    $this->actingAs(User::factory()->reviewer()->create());

    Livewire::test(NationalIdLookup::class)
        ->set('nationalId', '120150000003')
        ->call('search')
        ->assertSee('فرد من عائلة الموظف')
        ->assertSee('الرقم المبحوث عنه')
        ->assertSee('سالم علي محمد')
        ->assertSee('فاطمة أحمد سالم')
        ->assertDontSee($son->card_number);
});

it('hides cancelled family members', function () {
    ['spouse' => $spouse] = lookupFamily();
    $spouse->cancel(BeneficiaryCancellationReason::EmployeeDuplicate);
    $this->actingAs(User::factory()->cardDelivery()->create());

    Livewire::test(NationalIdLookup::class)
        ->set('nationalId', '119900000001')
        ->call('search')
        ->assertSee('محمد سالم علي')
        ->assertDontSee('فاطمة أحمد سالم');
});

it('says when nothing matches', function () {
    $this->actingAs(User::factory()->cardDelivery()->create());

    Livewire::test(NationalIdLookup::class)
        ->set('nationalId', '199999999999')
        ->call('search')
        ->assertSee('لا توجد نتائج');
});

it('changes nothing in the database', function () {
    ['employee' => $employee, 'spouse' => $spouse] = lookupFamily();
    $this->actingAs(User::factory()->cardDelivery()->create());

    Livewire::test(NationalIdLookup::class)
        ->set('nationalId', '119900000001')
        ->call('search');

    expect($employee->fresh()->updated_at->equalTo($employee->updated_at))->toBeTrue()
        ->and($spouse->fresh()->updated_at->equalTo($spouse->updated_at))->toBeTrue()
        ->and($employee->fresh()->cards_delivered_at)->toBeNull();
});
