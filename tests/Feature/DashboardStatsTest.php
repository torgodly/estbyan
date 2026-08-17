<?php

use App\Filament\Widgets\EmployeeStatsOverview;
use App\Filament\Widgets\RegistrationStatsOverview;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Livewire\Livewire;

it('registers custom stats widgets on the dashboard and removes defaults', function () {
    $widgets = Filament::getWidgets();

    expect($widgets)->toContain(RegistrationStatsOverview::class)
        ->and($widgets)->toContain(EmployeeStatsOverview::class)
        ->and($widgets)->not->toContain(AccountWidget::class)
        ->and($widgets)->not->toContain(FilamentInfoWidget::class);
});

it('brands the admin panel for tax authority hr', function () {
    expect(Filament::getBrandName())->toBe('مصلحة الضرائب · الموارد البشرية')
        ->and((string) Filament::getBrandLogo())->toContain('images/brand/tax-authority.png')
        ->and(Filament::getFavicon())->toContain('images/brand/tax-authority.png');
});

it('renders registration and employee stats overview content', function () {
    $admin = User::factory()->create();

    Employee::factory()->count(2)->create(['is_active' => true]);
    MedicalRegistration::factory()->submitted()->create();
    MedicalRegistration::factory()->approved()->create();

    $this->actingAs($admin);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSeeLivewire(RegistrationStatsOverview::class)
        ->assertSeeLivewire(EmployeeStatsOverview::class)
        ->assertDontSeeLivewire(AccountWidget::class)
        ->assertDontSeeLivewire(FilamentInfoWidget::class);

    Livewire::test(RegistrationStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('بانتظار المراجعة')
        ->assertSee('قيد التعديل')
        ->assertSee('مقبول')
        ->assertSee('مرفوض');

    Livewire::test(EmployeeStatsOverview::class)
        ->assertSuccessful()
        ->assertSee('الموظفون')
        ->assertSee('نشطون')
        ->assertSee('قدّموا طلباً')
        ->assertSee('لديهم طلب معلّق');
});
