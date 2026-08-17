<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TaxHrAdminSeeder extends Seeder
{
    public const EMAIL = 'hr@tax.gov.ly';

    public const PASSWORD = 'Tax-HR!2026#Admin';

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'مصلحة الضرائب · الموارد البشرية',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->newLine();
        $this->command?->info('Tax Authority HR admin ready for the Filament panel (/admin)');
        $this->command?->line('  Email:    '.self::EMAIL);
        $this->command?->line('  Password: '.self::PASSWORD);
        $this->command?->newLine();
    }
}
