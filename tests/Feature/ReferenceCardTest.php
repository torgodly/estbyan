<?php

use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Services\ReferenceCardGenerator;
use App\Support\LibyanNationalId;

it('builds a png reference card with employee identity fields', function () {
    $employee = Employee::factory()->create([
        'national_id' => LibyanNationalId::generate(),
        'full_name' => 'إبراهيم صالح',
        'employee_number' => '8123',
    ]);

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => '8123',
        'national_id' => $employee->national_id,
        'full_name' => 'إبراهيم صالح',
        'reference_number' => 'SC26-00999',
    ]);

    $png = app(ReferenceCardGenerator::class)->png($registration);

    expect($png)->toStartWith("\x89PNG")
        ->and(strlen($png))->toBeGreaterThan(20_000);

    $image = imagecreatefromstring($png);

    expect($image)->not->toBeFalse()
        ->and(imagesx($image))->toBe(900)
        ->and(imagesy($image))->toBe(1180);

    imagedestroy($image);
});

it('downloads the redesigned reference card for a session registration', function () {
    $employee = Employee::factory()->create([
        'national_id' => LibyanNationalId::generate(),
        'full_name' => 'منى العابد',
    ]);

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => $employee->full_name,
        'reference_number' => 'SC26-00123',
    ]);

    $this->withSession([
        'registration_id' => $registration->id,
        'reference_download_id' => $registration->id,
    ])
        ->get(route('registration.reference-card', $registration))
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/png')
        ->assertHeader('content-disposition', 'attachment; filename="tax-SC26-00123.png"');
});
