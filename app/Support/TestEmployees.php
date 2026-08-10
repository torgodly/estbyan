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
                'full_name' => 'محمد سالم علي الفرجاني',
                'employee_number' => '1047',
                'national_id' => '119941111111',
            ],
            [
                'full_name' => 'فاطمة خالد عمر الزوي',
                'employee_number' => '2381',
                'national_id' => '219872222222',
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
