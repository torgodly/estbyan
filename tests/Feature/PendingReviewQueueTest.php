<?php

use App\Enums\RegistrationStatus;
use App\Filament\Resources\PendingReviews\Pages\ListPendingReviews;
use App\Filament\Resources\PendingReviews\Pages\ViewPendingReview;
use App\Filament\Resources\PendingReviews\PendingReviewResource;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\ReviewerAccounts;
use App\Support\ReviewerQueueSplitter;
use Filament\Tables\Enums\FiltersLayout;
use Livewire\Livewire;

beforeEach(function () {
    travelToTripoliTime('2026-09-14 10:00:00');
});

it('shows reviewers only submitted requests without tabs', function () {
    $reviewer = User::factory()->reviewer()->create();

    $pending = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف بانتظار المراجعة',
    ]);
    $approved = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'موظف مقبول للمراجعين',
    ]);
    $declined = MedicalRegistration::factory()->declined()->create([
        'full_name' => 'موظف مرفوض للمراجعين',
    ]);
    $draft = MedicalRegistration::factory()->create([
        'full_name' => 'موظف مسودة للمراجعين',
    ]);
    $editing = MedicalRegistration::factory()->editing()->create([
        'full_name' => 'موظف قيد التعديل للمراجعين',
    ]);

    $this->actingAs($reviewer);

    $page = Livewire::test(ListPendingReviews::class)
        ->assertSuccessful()
        ->assertSee('طلبات بانتظار المراجعة')
        ->assertSee('موظف بانتظار المراجعة')
        ->assertDontSee('موظف مقبول للمراجعين')
        ->assertDontSee('موظف مرفوض للمراجعين')
        ->assertDontSee('موظف مسودة للمراجعين')
        ->assertDontSee('موظف قيد التعديل للمراجعين')
        ->assertDontSee('قيد التعديل')
        ->assertDontSee('مسودة')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$approved, $declined, $draft, $editing]);

    $table = $page->instance()->getTable();

    expect($table->getFilters())->toHaveKey('city')
        ->and($table->isSearchable())->toBeTrue()
        ->and($table->isFilterable())->toBeTrue()
        ->and($table->getFiltersLayout())->toBe(FiltersLayout::AboveContent);
});

it('searches the review queue like the requests table', function () {
    $reviewer = User::factory()->reviewer()->create();

    $match = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'أحمد المبروك للمراجعة',
        'employee_number' => '77881',
    ]);
    $other = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف آخر للمراجعة',
        'employee_number' => '11002',
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ListPendingReviews::class)
        ->assertCanSeeTableRecords([$match, $other])
        ->searchTable('77881')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

it('filters the review queue by city', function () {
    $reviewer = User::factory()->reviewer()->create();

    MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف طرابلس للمراجعة',
        'city' => 'tripoli',
    ]);
    MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف سبها للمراجعة',
        'city' => 'sebha',
    ]);

    $this->actingAs($reviewer);

    Livewire::test(ListPendingReviews::class)
        ->assertSee('موظف طرابلس للمراجعة')
        ->assertSee('موظف سبها للمراجعة')
        ->filterTable('city', 'tripoli')
        ->assertSee('موظف طرابلس للمراجعة')
        ->assertDontSee('موظف سبها للمراجعة');
});

it('splits pending requests across reviewers without storing an assignment', function () {
    $reviewers = User::factory()->reviewer()->count(4)->create();
    $pending = MedicalRegistration::factory()->submitted()->count(12)->create();

    $seen = [];

    foreach ($reviewers as $reviewer) {
        $this->actingAs($reviewer);

        $ids = PendingReviewResource::getEloquentQuery()->pluck('id')->all();

        expect(array_intersect($seen, $ids))->toBeEmpty();

        Livewire::test(ListPendingReviews::class)
            ->assertCanSeeTableRecords($pending->whereIn('id', $ids)->values())
            ->assertCanNotSeeTableRecords($pending->whereNotIn('id', $ids)->values());

        $seen = [...$seen, ...$ids];
    }

    expect($seen)->toHaveCount($pending->count())
        ->and(collect($seen)->sort()->values()->all())
        ->toEqual($pending->pluck('id')->sort()->values()->all());

    $foreign = $pending->first(
        fn (MedicalRegistration $registration): bool => ! ReviewerQueueSplitter::owns($reviewers[0], $registration),
    );

    expect($foreign)->not->toBeNull();

    $this->actingAs($reviewers[0]);

    expect(PendingReviewResource::canView($foreign))->toBeFalse();

    expect($this->get(PendingReviewResource::getUrl('view', ['record' => $foreign]))->status())
        ->toBeIn([403, 404]);
});

it('keeps the four reviewer slots stable when new requests arrive', function () {
    $accounts = collect(ReviewerAccounts::definitions())->map(
        fn (array $definition): User => User::factory()->reviewer()->create([
            'name' => $definition['name'],
            'email' => $definition['email'],
        ]),
    );

    User::factory()->reviewer()->create([
        'email' => 'extra-reviewer@example.com',
    ]);

    $firstBatch = MedicalRegistration::factory()->submitted()->count(8)->create();
    $ownedByFirst = $firstBatch->filter(
        fn (MedicalRegistration $registration): bool => ReviewerQueueSplitter::owns($accounts[0], $registration),
    );

    $later = MedicalRegistration::factory()->submitted()->create();
    $owner = $accounts[(int) $later->id % 4];

    expect(ReviewerQueueSplitter::owns($owner, $later))->toBeTrue()
        ->and($ownedByFirst->every(
            fn (MedicalRegistration $registration): bool => ReviewerQueueSplitter::owns($accounts[0], $registration),
        ))->toBeTrue();

    $this->actingAs($owner);

    Livewire::test(ListPendingReviews::class)
        ->assertCanSeeTableRecords([$later]);
});

it('redirects a reviewer back to the queue after approving and blocks the record', function () {
    $reviewer = User::factory()->reviewer()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();

    $this->actingAs($reviewer);

    Livewire::test(ViewPendingReview::class, ['record' => $registration->getRouteKey()])
        ->assertSuccessful()
        ->assertActionVisible('approve')
        ->assertActionVisible('decline')
        ->assertActionHidden('viewEmployee')
        ->assertActionHidden('downloadReferenceCard')
        ->assertActionHidden('downloadInsuranceCards')
        ->assertActionHidden('printInsuranceCards')
        ->callAction('approve', data: [
            'review_note' => 'مستوفي',
        ])
        ->assertHasNoActionErrors()
        ->assertRedirect(PendingReviewResource::getUrl());

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Approved)
        ->and($registration->reviewed_by)->toBe($reviewer->id)
        ->and(PendingReviewResource::canView($registration))->toBeFalse();

    expect($this->get(PendingReviewResource::getUrl('view', ['record' => $registration]))->status())
        ->toBeIn([403, 404]);
});

it('redirects a reviewer back to the queue after declining and blocks the record', function () {
    $reviewer = User::factory()->reviewer()->create();
    $registration = MedicalRegistration::factory()->submitted()->create();

    $this->actingAs($reviewer);

    Livewire::test(ViewPendingReview::class, ['record' => $registration->getRouteKey()])
        ->callAction('decline', data: [
            'review_note' => 'المستندات غير مكتملة',
        ])
        ->assertHasNoActionErrors()
        ->assertRedirect(PendingReviewResource::getUrl());

    $registration->refresh();

    expect($registration->status)->toBe(RegistrationStatus::Declined)
        ->and(PendingReviewResource::canView($registration))->toBeFalse();

    expect($this->get(PendingReviewResource::getUrl('view', ['record' => $registration]))->status())
        ->toBeIn([403, 404]);
});
