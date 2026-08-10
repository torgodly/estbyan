<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SupportAdminSeeder extends Seeder
{
    public const EMAIL = 'support@smartcare.com.ly';

    public const PASSWORD = 'Sc-Support!2026#Admin';

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Support Admin',
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
            ],
        );

        $this->command?->newLine();
        $this->command?->info('Support admin ready for the Filament panel (/admin)');
        $this->command?->line('  Email:    '.self::EMAIL);
        $this->command?->line('  Password: '.self::PASSWORD);
        $this->command?->newLine();
    }
}
