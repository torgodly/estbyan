<?php

use App\Enums\RegistrationStatus;
use App\Filament\Pages\CityRegistrationsReport;
use App\Models\MedicalRegistration;
use App\Models\User;
use App\Support\CityRegistrationsReport as CityRegistrationsReportBuilder;
use Livewire\Livewire;

it('counts every registration status for each city', function () {
    MedicalRegistration::factory()->approved()->create(['city' => 'tripoli']);
    MedicalRegistration::factory()->approved()->create(['city' => 'tripoli']);
    MedicalRegistration::factory()->declined()->create(['city' => 'tripoli']);
    MedicalRegistration::factory()->create(['city' => 'tripoli']);
    MedicalRegistration::factory()->submitted()->create(['city' => 'benghazi']);
    MedicalRegistration::factory()->editing()->create(['city' => 'benghazi']);
    MedicalRegistration::factory()->approved()->create(['city' => null]);

    $report = CityRegistrationsReportBuilder::build();
    $byKey = collect($report['cities'])->keyBy('key');

    expect($report['totals']['all'])->toBe(7)
        ->and($report['totals'][RegistrationStatus::Approved->value])->toBe(3)
        ->and($report['totals'][RegistrationStatus::Declined->value])->toBe(1)
        ->and($report['totals'][RegistrationStatus::Submitted->value])->toBe(1)
        ->and($report['totals'][RegistrationStatus::Editing->value])->toBe(1)
        ->and($report['totals'][RegistrationStatus::Draft->value])->toBe(1)
        ->and($byKey['tripoli']['label'])->toBe('طرابلس')
        ->and($byKey['tripoli']['counts'][RegistrationStatus::Approved->value])->toBe(2)
        ->and($byKey['tripoli']['counts'][RegistrationStatus::Declined->value])->toBe(1)
        ->and($byKey['tripoli']['counts'][RegistrationStatus::Draft->value])->toBe(1)
        ->and($byKey['tripoli']['total'])->toBe(4)
        ->and($byKey['benghazi']['counts'][RegistrationStatus::Submitted->value])->toBe(1)
        ->and($byKey['benghazi']['counts'][RegistrationStatus::Editing->value])->toBe(1)
        ->and($byKey['benghazi']['total'])->toBe(2)
        ->and($byKey['']['label'])->toBe('غير محددة')
        ->and($byKey['']['counts'][RegistrationStatus::Approved->value])->toBe(1)
        ->and($byKey['sebha']['total'])->toBe(0)
        ->and($report['cities'][0]['key'])->toBe('tripoli');
});

it('renders the city registrations report page for admins', function () {
    $admin = User::factory()->create();

    MedicalRegistration::factory()->approved()->create(['city' => 'misrata']);
    MedicalRegistration::factory()->declined()->create(['city' => 'misrata']);

    $this->actingAs($admin);

    Livewire::test(CityRegistrationsReport::class)
        ->assertSuccessful()
        ->assertSee('التسجيل حسب المدينة')
        ->assertSee('التوزيع حسب المدينة')
        ->assertSee('مصراته')
        ->assertSee('طرابلس')
        ->assertSee('مقبول')
        ->assertSee('مرفوض')
        ->assertSee('مسودة')
        ->assertSee('مُرسَل')
        ->assertSee('قيد التعديل')
        ->assertSee('الإجمالي');
});
