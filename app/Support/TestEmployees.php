<?php

namespace App\Support;

class TestEmployees
{
    /**
     * Fixed employees used for manual / automated registration testing.
     *
     * @return list<array{full_name: string, employee_number: string, national_id: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'full_name' => 'عمر عبدالله الزروق المسيميط',
                'employee_number' => '007017',
                'national_id' => '119730351644',
            ],
            [
                'full_name' => 'هاجر فرج عيسى محمد',
                'employee_number' => '001078',
                'national_id' => '219770149572',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function employeeNumbers(): array
    {
        return array_column(self::definitions(), 'employee_number');
    }

    /**
     * @return list<string>
     */
    public static function nationalIds(): array
    {
        return array_column(self::definitions(), 'national_id');
    }
}
