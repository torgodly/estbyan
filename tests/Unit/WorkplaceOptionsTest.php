<?php

use App\Support\WorkplaceOptions;
use Tests\TestCase;

uses(TestCase::class);

it('resolves workplace keys from arabic labels', function () {
    expect(WorkplaceOptions::keyForLabel('الإدارة العامة'))->toBe('general_admin')
        ->and(WorkplaceOptions::keyForLabel('سبها'))->toBe('sebha')
        ->and(WorkplaceOptions::keyForLabel('صبراته صرمان'))->toBe('sabratha_sorman')
        ->and(WorkplaceOptions::keyForLabel('فرع بني وليد'))->toBe('bani_walid');
});

it('returns null for unknown workplaces', function () {
    expect(WorkplaceOptions::keyForLabel('مكان غير موجود'))->toBeNull();
});
