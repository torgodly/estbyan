<?php

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\RegistrationReviewLog;
use App\Models\ReviewFlag;
use App\Models\User;
use App\Services\InsuranceCardNumberAssigner;

it('soft deletes core models and keeps them restorable', function (string $modelClass) {
    $record = match ($modelClass) {
        User::class => User::factory()->create(),
        Employee::class => Employee::factory()->create(),
        MedicalRegistration::class => MedicalRegistration::factory()->create(),
        Beneficiary::class => Beneficiary::factory()->create(),
        RegistrationReviewLog::class => RegistrationReviewLog::factory()->create(),
        ReviewFlag::class => ReviewFlag::factory()->create(),
    };

    $record->delete();

    expect($modelClass::query()->whereKey($record->getKey())->exists())->toBeFalse()
        ->and($modelClass::withTrashed()->whereKey($record->getKey())->exists())->toBeTrue();

    $trashed = $modelClass::withTrashed()->findOrFail($record->getKey());
    $trashed->restore();

    expect($modelClass::query()->whereKey($record->getKey())->exists())->toBeTrue();
})->with([
    'users' => User::class,
    'employees' => Employee::class,
    'medical registrations' => MedicalRegistration::class,
    'beneficiaries' => Beneficiary::class,
    'registration review logs' => RegistrationReviewLog::class,
    'review flags' => ReviewFlag::class,
]);

it('keeps soft-deleted card numbers reserved', function () {
    $employee = Employee::factory()->create();
    $employee->forceFill(['card_number' => '87654321'])->saveQuietly();
    $employee->delete();

    $other = Employee::factory()->make([
        'card_number' => null,
    ]);

    app(InsuranceCardNumberAssigner::class)->fillEmployee($other);

    expect($other->card_number)->not->toBeNull()
        ->and($other->card_number)->not->toBe('87654321');
});
