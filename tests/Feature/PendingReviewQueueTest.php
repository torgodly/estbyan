<?php

use App\Enums\RegistrationStatus;
use App\Filament\Resources\PendingReviews\Pages\ListPendingReviews;
use App\Filament\Resources\PendingReviews\Pages\ViewPendingReview;
use App\Filament\Resources\PendingReviews\PendingReviewResource;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

it('shows reviewers only submitted requests without filters tabs or search', function () {
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

    expect($table->getFilters())->toBeEmpty()
        ->and($table->isSearchable())->toBeFalse()
        ->and($table->isFilterable())->toBeFalse();
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
