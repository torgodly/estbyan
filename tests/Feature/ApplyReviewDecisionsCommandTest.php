<?php

use App\Enums\BeneficiaryRelationship;
use App\Enums\ReviewFlagReason;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;

/**
 * The records behind four of the October 2026 decisions, as they were in production.
 *
 * @return array<string, mixed>
 */
function octoberReviewRecords(): array
{
    $father = Beneficiary::factory()->create([
        'id' => 23471,
        'medical_registration_id' => MedicalRegistration::factory()->approved()->create()->id,
        'relationship' => BeneficiaryRelationship::Father,
        'national_id' => '119650315704',
    ]);

    $printedMother = Beneficiary::factory()->create([
        'id' => 61697,
        'medical_registration_id' => MedicalRegistration::factory()->approved()->create()->id,
        'relationship' => BeneficiaryRelationship::Mother,
        'national_id' => '219590162678',
        'card_printed_at' => now(),
    ]);
    $duplicateMother = Beneficiary::factory()->create([
        'id' => 49459,
        'medical_registration_id' => MedicalRegistration::factory()->approved()->create(['beneficiaries_count' => 1])->id,
        'relationship' => BeneficiaryRelationship::Mother,
        'national_id' => '219590162687',
    ]);

    $emptyRecord = Employee::factory()->create(['employee_number' => '004131', 'national_id' => '120000384414']);
    $keptRecord = MedicalRegistration::factory()->approved()->create([
        'employee_id' => Employee::factory()->create(['employee_number' => '040285', 'national_id' => '120000384411'])->id,
    ])->employee;

    $wife = Beneficiary::factory()->create([
        'id' => 60342,
        'medical_registration_id' => MedicalRegistration::factory()->declined()->create()->id,
        'relationship' => BeneficiaryRelationship::Spouse,
        'national_id' => '219870429691',
    ]);

    foreach ([$father, $printedMother, $duplicateMother, $emptyRecord, $keptRecord, $wife] as $person) {
        ReviewFlag::raise($person, ReviewFlagReason::PossibleDuplicateEmployee, 'x');
    }

    return compact('father', 'printedMother', 'duplicateMother', 'emptyRecord', 'keptRecord', 'wife');
}

it('applies each decision and closes its review flags', function () {
    $records = octoberReviewRecords();

    $this->artisan('review:apply-decisions-2026-10', ['--force' => true])
        ->expectsOutputToContain('Applied 4 decision(s) and closed 6 review flag(s).')
        ->assertSuccessful();

    expect($records['father']->fresh())->not->toBeNull()
        ->and($records['father']->fresh()->isCancelled())->toBeFalse()
        ->and($records['printedMother']->fresh())->not->toBeNull()
        ->and(Beneficiary::queryWithCancelled()->find(49459))->toBeNull()
        ->and($records['duplicateMother']->medicalRegistration->fresh()->beneficiaries_count)->toBe(0)
        ->and($records['emptyRecord']->fresh()->is_active)->toBeFalse()
        ->and($records['keptRecord']->fresh()->is_active)->toBeTrue()
        ->and(Beneficiary::queryWithCancelled()->find(60342)->isCancelled())->toBeTrue()
        ->and(ReviewFlag::query()->open()->count())->toBe(0)
        ->and(ReviewFlag::query()->first()->resolution_note)->toStartWith('قرار تلقائي:');
});

it('skips a decision when the record no longer matches what the decision was based on', function () {
    $records = octoberReviewRecords();
    $records['duplicateMother']->markCardPrinted();
    MedicalRegistration::factory()->submitted()->create(['employee_id' => $records['emptyRecord']->id]);

    $this->artisan('review:apply-decisions-2026-10', ['--force' => true])
        ->expectsOutputToContain('Applied 2 decision(s) and closed 2 review flag(s).')
        ->assertSuccessful();

    expect(Beneficiary::query()->find(49459))->not->toBeNull()
        ->and($records['emptyRecord']->fresh()->is_active)->toBeTrue();
});

it('changes nothing in dry-run mode and nothing twice', function () {
    $records = octoberReviewRecords();

    $this->artisan('review:apply-decisions-2026-10', ['--dry-run' => true])
        ->expectsOutputToContain('4 decision(s) ready')
        ->assertSuccessful();

    expect(ReviewFlag::query()->open()->count())->toBe(6)
        ->and($records['emptyRecord']->fresh()->is_active)->toBeTrue();

    $this->artisan('review:apply-decisions-2026-10', ['--force' => true])->assertSuccessful();
    $this->artisan('review:apply-decisions-2026-10', ['--force' => true])
        ->expectsOutputToContain('0 decision(s) ready')
        ->assertSuccessful();
});
