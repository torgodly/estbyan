<?php

use App\Filament\Pages\ChronicDiseasesReport;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\ManageRegistrationSettings;
use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Filament\Resources\MedicalRegistrations\Pages\ListMedicalRegistrations;
use App\Filament\Resources\PendingReviews\Pages\ListPendingReviews;
use App\Filament\Resources\PendingReviews\PendingReviewResource;
use App\Models\User;
use Livewire\Livewire;

it('keeps reviewers off the full admin pages and on the review queue', function () {
    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($reviewer);

    Livewire::test(ListPendingReviews::class)->assertSuccessful();
    Livewire::test(Dashboard::class)->assertRedirect(PendingReviewResource::getUrl());
    Livewire::test(ListMedicalRegistrations::class)->assertForbidden();
    Livewire::test(ListEmployees::class)->assertForbidden();
    Livewire::test(ManageRegistrationSettings::class)->assertForbidden();
    Livewire::test(ChronicDiseasesReport::class)->assertForbidden();
});

it('keeps hr users off the reviewer queue', function () {
    $hr = User::factory()->hr()->create();

    $this->actingAs($hr);

    Livewire::test(ListPendingReviews::class)->assertForbidden();
    Livewire::test(ListMedicalRegistrations::class)->assertSuccessful();
    Livewire::test(Dashboard::class)->assertSuccessful();
});
