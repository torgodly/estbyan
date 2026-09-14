<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\PendingReviews\PendingReviewResource;
use App\Filament\Widgets\CoverageStatsOverview;
use App\Filament\Widgets\EmployeeStatsOverview;
use App\Filament\Widgets\RegistrationStatsOverview;
use App\Http\Middleware\EnsureReviewerWorkingHours;
use App\Models\User;
use App\Support\ReviewerWorkingHours;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $taxLogoUrl = asset('images/brand/tax-authority.png');

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->brandName('مصلحة الضرائب · الموارد البشرية')
            ->brandLogo(fn (): HtmlString => new HtmlString(
                '<img src="'.e($taxLogoUrl).'" alt="مصلحة الضرائب" class="fi-logo tax-admin-logo">'
            ))
            ->darkModeBrandLogo($taxLogoUrl)
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/brand/tax-authority.png'))
            ->colors([
                'primary' => Color::hex('#0f2744'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->homeUrl(function (): string {
                if (User::authenticatedIsReviewer()) {
                    return PendingReviewResource::getUrl();
                }

                return Dashboard::getUrl();
            })
            ->widgets([
                CoverageStatsOverview::class,
                RegistrationStatsOverview::class,
                EmployeeStatsOverview::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureReviewerWorkingHours::class,
            ])
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): View => view('filament.hooks.reviewer-hours-banner', [
                    'isClosed' => ReviewerWorkingHours::isClosed(),
                    'sessionEnded' => session('reviewer_hours_ended') === true,
                    'title' => ReviewerWorkingHours::bannerTitle(),
                    'endedTitle' => ReviewerWorkingHours::sessionEndedTitle(),
                    'body' => ReviewerWorkingHours::bannerBody(),
                    'organization' => ReviewerWorkingHours::bannerOrganization(),
                ]),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                function (): string|View {
                    if (! User::authenticatedIsReviewer() || ReviewerWorkingHours::isClosed()) {
                        return '';
                    }

                    return view('filament.hooks.reviewer-hours-watch', [
                        'millisecondsUntilClose' => ReviewerWorkingHours::millisecondsUntilClose(),
                    ]);
                },
            );
    }
}
