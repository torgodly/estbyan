<?php

use App\Console\Commands\RevertAddedNationalIdUpdatesCommand;
use App\Models\Employee;
use Illuminate\Support\Facades\Artisan;

it('restores the eight employees overwritten by the added national ids import', function () {
    Employee::factory()->create([
        'employee_number' => '012286',
        'full_name' => 'عبدالله طارق عبدالله',
    ]);
    Employee::factory()->create([
        'employee_number' => '006236',
        'full_name' => 'حمزة محمد محمد العباسي',
        'workplace' => 'tajoura',
    ]);
    Employee::factory()->create([
        'employee_number' => '040039',
        'full_name' => 'ريان محمد عبدالسلام',
    ]);
    Employee::factory()->create([
        'employee_number' => '022137',
        'full_name' => 'رؤئ عبدالله ابراهيم نصر',
    ]);
    Employee::factory()->create([
        'employee_number' => '006243',
        'full_name' => 'عبدالعزيز عبدالسلام احمد شبالة',
        'office' => null,
    ]);
    Employee::factory()->create([
        'employee_number' => '006245',
        'full_name' => 'احمد علي عيسى أبولقاسم',
        'office' => null,
    ]);
    Employee::factory()->create([
        'employee_number' => '000426',
        'full_name' => 'رندة سالم وحيدة الطبولي',
        'workplace' => 'general_admin',
        'office' => 'شؤون الرئيس',
    ]);
    Employee::factory()->create([
        'employee_number' => '040123',
        'full_name' => 'فراس فيصل نوري المسماري',
    ]);

    Artisan::call('employees:revert-added-national-id-updates');

    expect(Employee::query()->where('employee_number', '012286')->value('full_name'))->toBe('عبدالله طارق عبدالله بوشعاله')
        ->and(Employee::query()->where('employee_number', '006236')->first()?->only(['full_name', 'workplace']))->toBe([
            'full_name' => 'حمزة احمد سالم البربار',
            'workplace' => 'sorman',
        ])
        ->and(Employee::query()->where('employee_number', '040039')->value('full_name'))->toBe('ريان محمد عبدالسلام الشريدي')
        ->and(Employee::query()->where('employee_number', '022137')->value('full_name'))->toBe('رؤي عبدالله ابراهيم تصر')
        ->and(Employee::query()->where('employee_number', '006243')->first()?->only(['full_name', 'office']))->toBe([
            'full_name' => 'عبدالعزيز عبدالسلام احمد شبانة',
            'office' => 'ابو عيسى',
        ])
        ->and(Employee::query()->where('employee_number', '006245')->first()?->only(['full_name', 'office']))->toBe([
            'full_name' => 'احمد علي عيسي المحروق',
            'office' => 'ابو عيسى',
        ])
        ->and(Employee::query()->where('employee_number', '000426')->first()?->only(['full_name', 'workplace', 'office']))->toBe([
            'full_name' => 'رندة سالم اوحيدة الطبولي',
            'workplace' => null,
            'office' => null,
        ])
        ->and(Employee::query()->where('employee_number', '040123')->value('full_name'))->toBe('فيصل نوري المسماري')
        ->and(Artisan::output())->toContain('Restored 8 employees')
        ->and(RevertAddedNationalIdUpdatesCommand::restorations())->toHaveCount(8);
});

it('skips missing employee numbers', function () {
    Artisan::call('employees:revert-added-national-id-updates');

    $output = Artisan::output();

    expect($output)->toContain('Missing employee #012286')
        ->and($output)->toContain('Restored 0 employees, 8 missing');
});
