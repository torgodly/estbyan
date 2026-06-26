<?php

namespace App\Models;

use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'employee_number',
    'national_id',
    'date_of_birth',
    'full_name',
    'is_active',
])]
class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function medicalRegistrations(): HasMany
    {
        return $this->hasMany(MedicalRegistration::class);
    }

    public static function findForVerification(string $employeeNumber, string $nationalId, string $dateOfBirth): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->where('employee_number', $employeeNumber)
            ->where('national_id', $nationalId)
            ->whereDate('date_of_birth', $dateOfBirth)
            ->first();
    }
}
