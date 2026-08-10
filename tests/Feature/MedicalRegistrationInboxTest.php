<?php

use App\Enums\RegistrationStatus;
use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use App\Filament\Resources\MedicalRegistrations\Pages\ListMedicalRegistrations;
use App\Models\MedicalRegistration;
use App\Models\User;
use Livewire\Livewire;

it('does not allow creating or editing registrations from the admin panel', function () {
    $registration = MedicalRegistration::factory()->submitted()->create();

    expect(MedicalRegistrationResource::canCreate())->toBeFalse()
        ->and(MedicalRegistrationResource::canEdit($registration))->toBeFalse()
        ->and(MedicalRegistrationResource::getPages())->not->toHaveKeys(['create', 'edit']);
});

it('defaults to the pending review tab and filters records', function () {
    $admin = User::factory()->create();

    $pending = MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف بانتظار',
    ]);
    $approved = MedicalRegistration::factory()->approved()->create([
        'full_name' => 'موظف مقبول',
    ]);
    MedicalRegistration::factory()->create([
        'full_name' => 'موظف مسودة',
        'status' => RegistrationStatus::Draft,
    ]);

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->assertSuccessful()
        ->assertSee('بانتظار المراجعة')
        ->assertSee('موظف بانتظار')
        ->assertDontSee('موظف مقبول')
        ->assertDontSee('موظف مسودة')
        ->set('activeTab', 'approved')
        ->assertSee('موظف مقبول')
        ->assertDontSee('موظف بانتظار')
        ->set('activeTab', 'all')
        ->assertSee('موظف بانتظار')
        ->assertSee('موظف مقبول')
        ->assertSee('موظف مسودة');

    expect($pending->isPendingReview())->toBeTrue()
        ->and($approved->isApproved())->toBeTrue();
});

it('filters submissions by workplace', function () {
    $admin = User::factory()->create();

    MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف طرابلس',
        'workplace' => 'tripoli',
    ]);
    MedicalRegistration::factory()->submitted()->create([
        'full_name' => 'موظف سبها',
        'workplace' => 'sebha',
    ]);

    $this->actingAs($admin);

    Livewire::test(ListMedicalRegistrations::class)
        ->assertSee('موظف طرابلس')
        ->assertSee('موظف سبها')
        ->filterTable('workplace', 'tripoli')
        ->assertSee('موظف طرابلس')
        ->assertDontSee('موظف سبها');
});
