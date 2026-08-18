<?php

use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Observers\DualWriteObserver;
use App\Support\DatabaseCutover\DualWrite;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

afterEach(function () {
    foreach ([
        database_path('testing-dual-source.sqlite'),
        database_path('testing-dual-target.sqlite'),
    ] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    config([
        'database.cutover.dual_write' => false,
        'database.default' => 'sqlite',
    ]);
});

it('mirrors eloquent writes to the cutover target while dual-write is enabled', function () {
    $sourcePath = database_path('testing-dual-source.sqlite');
    $targetPath = database_path('testing-dual-target.sqlite');

    foreach ([$sourcePath, $targetPath] as $path) {
        if (is_file($path)) {
            unlink($path);
        }

        touch($path);
    }

    config([
        'database.connections.dual_source' => [
            'driver' => 'sqlite',
            'database' => $sourcePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.connections.dual_target' => [
            'driver' => 'sqlite',
            'database' => $targetPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.default' => 'dual_source',
        'database.cutover.dual_write' => true,
        'database.cutover.target' => 'dual_target',
    ]);

    Artisan::call('migrate', ['--database' => 'dual_source', '--force' => true]);
    Artisan::call('migrate', ['--database' => 'dual_target', '--force' => true]);

    Employee::observe(DualWriteObserver::class);
    MedicalRegistration::observe(DualWriteObserver::class);

    expect(DualWrite::enabled())->toBeTrue();

    $employee = Employee::factory()->create([
        'employee_number' => '777001',
        'full_name' => 'كتابة مزدوجة',
    ]);

    $registration = MedicalRegistration::factory()->create([
        'employee_id' => $employee->id,
        'employee_number' => $employee->employee_number,
        'full_name' => 'تسجيل مزدوج',
        'current_step' => 2,
    ]);

    expect(DB::connection('dual_target')->table('employees')->where('id', $employee->id)->value('full_name'))
        ->toBe('كتابة مزدوجة')
        ->and(DB::connection('dual_target')->table('medical_registrations')->where('id', $registration->id)->value('current_step'))
        ->toBe(2);

    $registration->update(['current_step' => 4]);

    expect(DB::connection('dual_target')->table('medical_registrations')->where('id', $registration->id)->value('current_step'))
        ->toBe(4);

    $registration->delete();

    expect(DB::connection('dual_target')->table('medical_registrations')->where('id', $registration->id)->exists())->toBeFalse()
        ->and(Schema::connection('dual_target')->hasTable('employees'))->toBeTrue();
});
