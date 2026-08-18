<?php

use App\Enums\RegistrationStatus;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use Illuminate\Support\Facades\Artisan;

it('imports employees from the tax authority spreadsheet', function () {
    $path = database_path('data/employees.xlsx');

    expect(is_file($path))->toBeTrue();

    Artisan::call('employees:import', ['path' => $path]);

    expect(Employee::query()->where('is_active', true)->count())->toBeGreaterThan(4000)
        ->and(Employee::query()->where('workplace', 'general_admin')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('workplace', 'tripoli')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('workplace', 'sebha')->where('is_active', true)->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '007017')->where('national_id', '119730351644')->exists())->toBeTrue()
        ->and(Employee::query()->where('employee_number', '007017')->value('office'))->toBe('مكتب نائب المدير العام')
        ->and(Employee::query()->where('employee_number', '001078')->value('office'))->toBe('مكتب الشؤون القانونية')
        ->and(Employee::query()->where('office', '/')->count())->toBe(0)
        ->and(Employee::query()->whereNotNull('date_of_birth')->count())->toBe(0);
});

it('backfills office without changing filled registrations or employee dates of birth', function () {
    $path = database_path('data/employees.xlsx');

    $employee = Employee::factory()->create([
        'employee_number' => '007017',
        'national_id' => '119730351644',
        'full_name' => 'اسم محفوظ في الطلب',
        'workplace' => 'tripoli',
        'office' => null,
        'date_of_birth' => '1973-05-01',
        'is_active' => true,
    ]);

    $registration = MedicalRegistration::factory()->submitted()->create([
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'national_id' => $employee->national_id,
        'full_name' => 'اسم محفوظ في الطلب',
        'workplace' => 'tripoli',
        'date_of_birth' => '1973-05-01',
        'phone' => '0912345678',
        'city' => 'tripoli',
        'address' => 'عنوان محفوظ',
        'family_status_document_path' => 'registrations/keep/family.pdf',
        'employee_photo_path' => 'registrations/keep/photo.jpg',
        'reference_number' => 'SC26-99999',
    ]);

    Artisan::call('employees:import', ['path' => $path]);

    $registration->refresh();
    $employee->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Submitted)
        ->and($registration->full_name)->toBe('اسم محفوظ في الطلب')
        ->and($registration->workplace)->toBe('tripoli')
        ->and($registration->date_of_birth?->format('Y-m-d'))->toBe('1973-05-01')
        ->and($registration->phone)->toBe('0912345678')
        ->and($registration->address)->toBe('عنوان محفوظ')
        ->and($registration->family_status_document_path)->toBe('registrations/keep/family.pdf')
        ->and($registration->employee_photo_path)->toBe('registrations/keep/photo.jpg')
        ->and($registration->reference_number)->toBe('SC26-99999')
        ->and($employee->office)->toBe('مكتب نائب المدير العام')
        ->and($employee->date_of_birth?->format('Y-m-d'))->toBe('1973-05-01');
});
