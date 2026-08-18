<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class TestEmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Seeding fake smoke-test employees…');

        Artisan::call('employees:seed-test', [], $this->command?->getOutput());

        $this->command?->newLine();
    }
}
