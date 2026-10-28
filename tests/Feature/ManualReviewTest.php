<?php

use App\Enums\BeneficiaryCancellationReason;
use App\Enums\BeneficiaryRelationship;
use App\Enums\ReviewFlagReason;
use App\Filament\Resources\ReviewFlags\Pages\ListReviewFlags;
use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\ReviewFlag;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('flags two employees whose national IDs differ by one digit and share the first names', function () {
    $first = Employee::factory()->create(['full_name' => 'حسام الدين اسماعيل محمد الهليب', 'national_id' => '120000384414']);
    $second = Employee::factory()->create(['full_name' => 'حسام الدين اسماعيل الهليب', 'national_id' => '120000384411']);
    Employee::factory()->create(['full_name' => 'خالد سالم علي', 'national_id' => '120000384412']);

    $this->artisan('review:flag-possible-duplicates')
        ->expectsOutputToContain('Flagged 2 person(s) for manual review.')
        ->assertSuccessful();

    expect(ReviewFlag::query()->pluck('flaggable_id')->sort()->values()->all())->toBe([$first->id, $second->id])
        ->and(ReviewFlag::query()->first()->reason)->toBe(ReviewFlagReason::PossibleDuplicateEmployee);
});

it('flags a family member with the same name as an employee but a different national ID', function () {
    $employee = Employee::factory()->create(['full_name' => 'أميرة طارق انبيه جاب الله', 'national_id' => '220020081723']);
    $registration = MedicalRegistration::factory()->approved()->create();
    $family = Beneficiary::factory()->create([
        'medical_registration_id' => $registration->id,
        'full_name' => 'اميرة طارق انبيه جاب الله',
        'relationship' => BeneficiaryRelationship::Spouse,
        'national_id' => '220050081723',
    ]);

    $this->artisan('review:flag-possible-duplicates')->assertSuccessful();

    $flag = ReviewFlag::query()->sole();

    expect($flag->flaggable->is($family))->toBeTrue()
        ->and($flag->reason)->toBe(ReviewFlagReason::PossibleEmployeeAsFamily)
        ->and($flag->note)->toContain($employee->employee_number);
});

it('flags the same family member under two employees when the IDs differ by swapped digits', function () {
    $first = Beneficiary::factory()->create([
        'medical_registration_id' => MedicalRegistration::factory()->approved()->create()->id,
        'full_name' => 'ساسية ميلاد الصغير',
        'relationship' => BeneficiaryRelationship::Mother,
        'national_id' => '219590162678',
        'date_of_birth' => '1959-01-01',
    ]);
    $second = Beneficiary::factory()->create([
        'medical_registration_id' => MedicalRegistration::factory()->approved()->create()->id,
        'full_name' => 'ساسية ميلاد ميلاد الصغير',
        'relationship' => BeneficiaryRelationship::Mother,
        'national_id' => '219590162687',
        'date_of_birth' => '1959-01-01',
    ]);

    $this->artisan('review:flag-possible-duplicates')->assertSuccessful();

    expect(ReviewFlag::query()->where('reason', ReviewFlagReason::PossibleDuplicateFamilyMember)->pluck('flaggable_id')->sort()->values()->all())
        ->toBe([$first->id, $second->id]);
});

it('does not duplicate flags or reopen resolved ones when run again', function () {
    Employee::factory()->create(['full_name' => 'امحمد زكريا امحمد عبدالقادر', 'national_id' => '120060728368']);
    Employee::factory()->create(['full_name' => 'امحمد زكريا امحمد عبدالقادر', 'national_id' => '120060729368']);

    $this->artisan('review:flag-possible-duplicates')->assertSuccessful();
    ReviewFlag::query()->first()->resolve(User::factory()->create(), 'شخصان مختلفان');
    $this->artisan('review:flag-possible-duplicates')->assertSuccessful();

    expect(ReviewFlag::query()->count())->toBe(2)
        ->and(ReviewFlag::query()->open()->count())->toBe(1);
});

it('does not change anything in dry-run mode', function () {
    Employee::factory()->create(['full_name' => 'امحمد زكريا امحمد عبدالقادر', 'national_id' => '120060728368']);
    Employee::factory()->create(['full_name' => 'امحمد زكريا امحمد عبدالقادر', 'national_id' => '120060729368']);

    $this->artisan('review:flag-possible-duplicates', ['--dry-run' => true])
        ->expectsOutputToContain('Would flag 2 person(s) for manual review.')
        ->assertSuccessful();

    expect(ReviewFlag::query()->count())->toBe(0);
});

it('flags instead of cancelling when a family member has an employee national ID but another first name', function () {
    $employee = Employee::factory()->create(['full_name' => 'محمد خالد بلقاسم بن طالب']);
    $family = Beneficiary::factory()->create([
        'medical_registration_id' => MedicalRegistration::factory()->approved()->create()->id,
        'full_name' => 'قاسم خالد بلقاسم بن طالب',
        'relationship' => BeneficiaryRelationship::Son,
        'national_id' => $employee->national_id,
    ]);

    $this->artisan('beneficiaries:cleanup-duplicates', ['--force' => true])
        ->expectsOutputToContain('Left unchanged for human review.')
        ->assertSuccessful();

    expect($family->fresh()->isCancelled())->toBeFalse()
        ->and(ReviewFlag::query()->sole()->reason)->toBe(ReviewFlagReason::EmployeeNationalIdNameMismatch);
});

it('lists open flags on the manual review page and records the review', function () {
    $user = User::factory()->smartCare()->create();
    $open = ReviewFlag::factory()->create();
    $resolved = ReviewFlag::factory()->resolved()->create();

    $this->actingAs($user);

    Livewire::test(ListReviewFlags::class)
        ->assertCanSeeTableRecords([$open])
        ->assertCanNotSeeTableRecords([$resolved])
        ->callAction(TestAction::make('resolve')->table($open), ['resolution_note' => 'شخصان مختلفان'])
        ->assertNotified('تم تسجيل المراجعة');

    expect($open->fresh()->isResolved())->toBeTrue()
        ->and($open->fresh()->resolved_by)->toBe($user->id)
        ->and($open->fresh()->resolution_note)->toBe('شخصان مختلفان');
});

it('shows flagged cancelled family members on the review page', function () {
    $family = Beneficiary::factory()->create([
        'medical_registration_id' => MedicalRegistration::factory()->approved()->create()->id,
        'full_name' => 'فاطمة سالم',
        'relationship' => BeneficiaryRelationship::Spouse,
    ]);
    $flag = ReviewFlag::raise($family, ReviewFlagReason::PossibleEmployeeAsFamily, 'ملاحظة');
    $family->cancel(BeneficiaryCancellationReason::EmployeeDuplicate);

    $this->actingAs(User::factory()->hr()->create());

    Livewire::test(ListReviewFlags::class)
        ->assertCanSeeTableRecords([$flag])
        ->assertSee('فاطمة سالم');
});

it('hides the manual review page from reviewers and delivery accounts', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create());

    Livewire::test(ListReviewFlags::class)->assertForbidden();
})->with(['reviewer', 'cardDelivery']);
