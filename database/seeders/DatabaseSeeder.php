<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SupportAdminSeeder::class,
            TaxHrAdminSeeder::class,
            EmployeeSeeder::class,
            TestEmployeeSeeder::class,
        ]);
    }
}
