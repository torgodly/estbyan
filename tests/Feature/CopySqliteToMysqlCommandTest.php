<?php

use App\Models\Beneficiary;
use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

afterEach(function () {
    foreach ([
        database_path('testing-copy-source.sqlite'),
        database_path('testing-copy-target.sqlite'),
    ] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
});

/**
 * @return array{0: string, 1: string}
 */
function prepareCopyConnections(): array
{
    $sourcePath = database_path('testing-copy-source.sqlite');
    $targetPath = database_path('testing-copy-target.sqlite');

    foreach ([$sourcePath, $targetPath] as $path) {
        if (is_file($path)) {
            unlink($path);
        }

        touch($path);
    }

    config([
        'database.connections.copy_source' => [
            'driver' => 'sqlite',
            'database' => $sourcePath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'database.connections.copy_target' => [
            'driver' => 'sqlite',
            'database' => $targetPath,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
    ]);

    return [$sourcePath, $targetPath];
}

it('refuses non-live copy when the application is not in maintenance mode', function () {
    expect(Artisan::call('db:copy-sqlite-to-mysql', [
        '--force' => true,
        '--source' => 'sqlite',
        '--target' => 'sqlite',
    ]))->toBe(1)
        ->and(Artisan::output())->toContain('--live');
});

it('refuses live copy when dual-write is disabled', function () {
    config(['database.cutover.dual_write' => false]);

    expect(Artisan::call('db:copy-sqlite-to-mysql', [
        '--live' => true,
        '--force' => true,
        '--source' => 'sqlite',
        '--target' => 'mysql_target',
    ]))->toBe(1)
        ->and(Artisan::output())->toContain('DB_DUAL_WRITE');
});

it('live-syncs registration data between connections while preserving ids and counts', function () {
    prepareCopyConnections();

    config([
        'database.cutover.dual_write' => true,
        'database.cutover.target' => 'copy_target',
    ]);

    Artisan::call('migrate', ['--database' => 'copy_source', '--force' => true]);

    $previousDefault = config('database.default');
    config(['database.default' => 'copy_source']);

    try {
        $user = User::factory()->create(['email' => 'copy-test@example.com']);
        $employee = Employee::factory()->create([
            'employee_number' => '888001',
            'full_name' => 'موظف نسخ',
        ]);
        $registration = MedicalRegistration::factory()->submitted()->create([
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'reference_number' => 'SC26-00042',
        ]);
        Beneficiary::factory()->create([
            'medical_registration_id' => $registration->id,
            'full_name' => 'مستفيد نسخ',
        ]);

        DB::connection('copy_source')->table('sessions')->insert([
            'id' => 'live-session-1',
            'payload' => base64_encode('payload'),
            'last_activity' => time(),
        ]);

        $sourceEmployees = DB::connection('copy_source')->table('employees')->count();
        $sourceRegistrations = DB::connection('copy_source')->table('medical_registrations')->count();
        $sourceBeneficiaries = DB::connection('copy_source')->table('beneficiaries')->count();
        $registrationId = $registration->id;
        $userId = $user->id;
    } finally {
        config(['database.default' => $previousDefault]);
    }

    expect(Artisan::call('db:copy-sqlite-to-mysql', [
        '--source' => 'copy_source',
        '--target' => 'copy_target',
        '--live' => true,
        '--force' => true,
        '--passes' => 1,
    ]))->toBe(0);

    expect(DB::connection('copy_target')->table('employees')->count())->toBe($sourceEmployees)
        ->and(DB::connection('copy_target')->table('medical_registrations')->count())->toBe($sourceRegistrations)
        ->and(DB::connection('copy_target')->table('beneficiaries')->count())->toBe($sourceBeneficiaries)
        ->and(DB::connection('copy_target')->table('users')->where('id', $userId)->exists())->toBeTrue()
        ->and(DB::connection('copy_target')->table('medical_registrations')->where('id', $registrationId)->value('reference_number'))
        ->toBe('SC26-00042')
        ->and(DB::connection('copy_target')->table('sessions')->where('id', 'live-session-1')->exists())->toBeTrue()
        ->and(Schema::connection('copy_target')->hasTable('cache'))->toBeTrue();
});

it('prunes orphan target rows during live sync', function () {
    prepareCopyConnections();

    config([
        'database.cutover.dual_write' => true,
        'database.cutover.target' => 'copy_target',
    ]);

    Artisan::call('migrate', ['--database' => 'copy_source', '--force' => true]);
    Artisan::call('migrate', ['--database' => 'copy_target', '--force' => true]);

    $previousDefault = config('database.default');
    config(['database.default' => 'copy_source']);

    try {
        Employee::factory()->create(['employee_number' => '888010']);
    } finally {
        config(['database.default' => $previousDefault]);
    }

    DB::connection('copy_target')->table('employees')->insert([
        'full_name' => 'يتيم',
        'national_id' => '000000000099',
        'employee_number' => 'orphan-1',
        'date_of_birth' => '1990-01-01',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(Artisan::call('db:copy-sqlite-to-mysql', [
        '--source' => 'copy_source',
        '--target' => 'copy_target',
        '--live' => true,
        '--force' => true,
        '--passes' => 1,
    ]))->toBe(0);

    expect(DB::connection('copy_target')->table('employees')->where('employee_number', 'orphan-1')->exists())->toBeFalse()
        ->and(DB::connection('copy_target')->table('employees')->where('employee_number', '888010')->exists())->toBeTrue();
});
