<?php

use App\Enums\RegistrationStatus;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Services\RegistrationReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('approves submitted and declined registrations', function () {
    $service = app(RegistrationReviewService::class);
    $reviewer = User::factory()->create();

    $submitted = MedicalRegistration::factory()->submitted()->create();
    $service->approve($submitted, $reviewer, 'موافق');
    $submitted->refresh();

    expect($submitted->status)->toBe(RegistrationStatus::Approved)
        ->and($submitted->review_note)->toBe('موافق')
        ->and($submitted->reviewed_by)->toBe($reviewer->id);

    $declined = MedicalRegistration::factory()->declined()->create();
    $service->approve($declined, $reviewer, null);
    $declined->refresh();

    expect($declined->status)->toBe(RegistrationStatus::Approved)
        ->and($declined->review_note)->toBeNull();
});

it('declines submitted and approved registrations with a required note', function () {
    $service = app(RegistrationReviewService::class);
    $reviewer = User::factory()->create();

    $submitted = MedicalRegistration::factory()->submitted()->create();
    $service->decline($submitted, $reviewer, 'نقص مستندات');
    $submitted->refresh();

    expect($submitted->status)->toBe(RegistrationStatus::Declined)
        ->and($submitted->review_note)->toBe('نقص مستندات');

    $approved = MedicalRegistration::factory()->approved()->create();
    $service->decline($approved, $reviewer, 'مراجعة لاحقة');
    $approved->refresh();

    expect($approved->status)->toBe(RegistrationStatus::Declined);
});

it('rejects invalid review transitions', function () {
    $service = app(RegistrationReviewService::class);
    $reviewer = User::factory()->create();
    $draft = MedicalRegistration::factory()->create();
    $approved = MedicalRegistration::factory()->approved()->create();
    $declined = MedicalRegistration::factory()->declined()->create();

    expect(fn () => $service->approve($draft, $reviewer))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->approve($approved, $reviewer))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->decline($draft, $reviewer, 'سبب'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->decline($declined, $reviewer, 'سبب'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->decline($approved, $reviewer, '   '))->toThrow(InvalidArgumentException::class);
});
