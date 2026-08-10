<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/employees.xlsx');

        if (! is_file($path)) {
            $this->command?->error("Employee spreadsheet missing: {$path}");

            return;
        }

        $this->command?->info('Importing employees from database/data/employees.xlsx …');

        Artisan::call('employees:import', ['path' => $path], $this->command?->getOutput());

        $this->command?->newLine();
    }
}
