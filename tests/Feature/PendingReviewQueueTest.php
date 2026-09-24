<?php

use App\Enums\RegistrationStatus;
use App\Filament\Resources\PendingReviews\Pages\ListPendingReviews;
use App\Filament\Resources\PendingReviews\Pages\ViewPendingReview;
use App\Filament\Resources\PendingReviews\PendingReviewResource;
use App\Models\MedicalRegistration;
use App\Models\User;
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

it('lets every reviewer see every pending request', function () {
    $reviewers = User::factory()->reviewer()->count(3)->create();
    $pending = MedicalRegistration::factory()->submitted()->count(6)->create();

    foreach ($reviewers as $reviewer) {
        $this->actingAs($reviewer);

        expect(PendingReviewResource::getEloquentQuery()->pluck('id')->sort()->values()->all())
            ->toEqual($pending->pluck('id')->sort()->values()->all())
            ->and(PendingReviewResource::canView($pending->first()))->toBeTrue();

        Livewire::test(ListPendingReviews::class)
            ->assertCanSeeTableRecords($pending);

        $this->get(PendingReviewResource::getUrl('view', ['record' => $pending->first()]))
            ->assertSuccessful();
    }
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
