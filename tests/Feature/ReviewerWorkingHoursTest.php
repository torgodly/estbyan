<?php

use App\Filament\Auth\Login;
use App\Filament\Resources\PendingReviews\PendingReviewResource;
use App\Models\User;
use App\Support\ReviewerWorkingHours;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;

it('follows tripoli reviewer hours', function (string $time, bool $open) {
    travelToTripoliTime($time);

    expect(ReviewerWorkingHours::isOpen())->toBe($open)
        ->and(ReviewerWorkingHours::isClosed())->toBe(! $open);
})->with([
    'before opening' => ['2026-09-14 06:59:59', false],
    'opening' => ['2026-09-14 07:00:00', true],
    'mid morning' => ['2026-09-14 10:00:00', true],
    'just before close' => ['2026-09-14 13:14:59', true],
    'closing' => ['2026-09-14 13:15:00', false],
    'afternoon' => ['2026-09-14 16:00:00', false],
    'night' => ['2026-09-14 23:59:59', false],
    'next morning before open' => ['2026-09-15 06:59:59', false],
    'next morning open' => ['2026-09-15 07:00:00', true],
]);

it('uses africa tripoli instead of the app utc timezone', function () {
    $this->travelTo(Carbon::parse('2026-09-14 11:14:59', 'UTC'));

    expect(ReviewerWorkingHours::isOpen())->toBeTrue();

    $this->travelTo(Carbon::parse('2026-09-14 11:15:00', 'UTC'));

    expect(ReviewerWorkingHours::isClosed())->toBeTrue();
});

it('counts milliseconds until the 1:15 pm tripoli close', function () {
    travelToTripoliTime('2026-09-14 13:14:00');

    expect(ReviewerWorkingHours::millisecondsUntilClose())->toBe(60_000)
        ->and(ReviewerWorkingHours::nextOpensAt()->toDateTimeString())->toBe('2026-09-15 07:00:00');
});

it('does not show a closed-day banner after 1:15 pm', function () {
    travelToTripoliTime('2026-09-14 13:15:00');

    $this->get('/admin/login')
        ->assertSuccessful()
        ->assertDontSee(ReviewerWorkingHours::bannerTitle(), false)
        ->assertDontSee(ReviewerWorkingHours::bannerBody(), false);
});

it('lets reviewers sign in after 1:15 pm', function () {
    travelToTripoliTime('2026-09-14 13:15:00');

    $reviewer = User::factory()->reviewer()->create();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $reviewer->email,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    assertAuthenticatedAs($reviewer);
});

it('keeps reviewers signed in after 1:15 pm', function () {
    travelToTripoliTime('2026-09-14 13:15:00');

    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($reviewer)
        ->get(PendingReviewResource::getUrl())
        ->assertSuccessful()
        ->assertDontSee('data-reviewer-session-watch', false);

    assertAuthenticatedAs($reviewer);
});

it('does not watch remaining session time while a reviewer is signed in', function () {
    travelToTripoliTime('2026-09-14 10:00:00');

    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($reviewer)
        ->get(PendingReviewResource::getUrl())
        ->assertSuccessful()
        ->assertDontSee('data-reviewer-session-watch', false)
        ->assertDontSee('data-close-in=', false);
});

it('still rejects a wrong reviewer password after hours', function () {
    travelToTripoliTime('2026-09-14 13:15:00');

    $reviewer = User::factory()->reviewer()->create();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $reviewer->email,
            'password' => 'wrong-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email'])
        ->assertDontSee(ReviewerWorkingHours::loginBlockedMessage());

    assertGuest();
});
