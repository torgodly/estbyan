<?php

use App\Enums\Gender;
use App\Livewire\MedicalRegistrationForm;
use App\Models\Employee;
use App\Rules\LibyanMobileNumber;
use App\Support\LibyanNationalId;
use Illuminate\Support\Facades\Validator;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

function employeeDetailsForm(): Testable
{
    $nationalId = LibyanNationalId::generate(Gender::Male, 1978);
    Employee::factory()->create(['national_id' => $nationalId, 'workplace' => 'tripoli']);

    return Livewire::test(MedicalRegistrationForm::class)
        ->set('nationalId', $nationalId)
        ->set('consent', true)
        ->call('verifyIdentity')
        ->set('dateOfBirth', '1978-01-01')
        ->set('bloodType', 'o_positive')
        ->set('city', 'tripoli')
        ->set('address', 'طرابلس');
}

it('accepts 10-digit numbers starting with 091, 092, 093 or 094', function (string $phone) {
    expect(Validator::make(['phone' => $phone], ['phone' => [new LibyanMobileNumber]])->passes())->toBeTrue();
})->with(['0910000000', '0921234567', '0937654321', '0949999999']);

it('rejects anything else', function (string $phone) {
    expect(Validator::make(['phone' => $phone], ['phone' => [new LibyanMobileNumber]])->passes())->toBeFalse();
})->with(['0951234567', '0901234567', '091123456', '09112345678', '914964440', '0210000000', '09a1234567', '٠٩١٢٣٤٥٦٧٨']);

it('cleans up what people usually type', function (string $typed, string $expected) {
    expect(LibyanMobileNumber::normalize($typed))->toBe($expected);
})->with([
    ['091 000 0000', '0910000000'],
    ['091-000-0000', '0910000000'],
    ['+218910000000', '0910000000'],
    ['00218920000000', '0920000000'],
]);

it('shows a clear error on the registration form for a wrong phone number', function () {
    employeeDetailsForm()
        ->set('phone', '0951234567')
        ->set('whatsapp', '12345')
        ->call('saveEmployeeDetails')
        ->assertHasErrors(['phone', 'whatsapp'])
        ->assertSee('يبدأ بـ 091 أو 092 أو 093 أو 094');
});

it('saves a correct phone number written with spaces or +218', function () {
    employeeDetailsForm()
        ->set('phone', '+218 91 234 5678')
        ->set('whatsapp', '')
        ->call('saveEmployeeDetails')
        ->assertHasNoErrors()
        ->assertSet('phone', '0912345678');
});
