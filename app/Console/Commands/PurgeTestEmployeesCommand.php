<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\MedicalRegistration;
use App\Support\RegistrationDocuments;
use App\Support\TestEmployees;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

#[Signature('employees:purge-test {--force : Skip the confirmation prompt}')]
#[Description('Delete the two test employees and any related registrations, beneficiaries, and documents')]
class PurgeTestEmployeesCommand extends Command
{
    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete the test employees and all related registration data?', false)) {
            $this->components->warn('Cancelled.');

            return self::SUCCESS;
        }

        $employeeNumbers = TestEmployees::employeeNumbers();
        $nationalIds = TestEmployees::nationalIds();

        $employees = Employee::query()
            ->whereIn('employee_number', $employeeNumbers)
            ->orWhereIn('national_id', $nationalIds)
            ->get();

        $registrations = MedicalRegistration::query()
            ->with('beneficiaries')
            ->where(function (Builder $query) use ($employees, $employeeNumbers, $nationalIds): void {
                if ($employees->isNotEmpty()) {
                    $query->whereIn('employee_id', $employees->modelKeys());
                }

                $query->orWhereIn('employee_number', $employeeNumbers)
                    ->orWhereIn('national_id', $nationalIds);
            })
            ->get();

        $deletedFiles = 0;

        DB::transaction(function () use ($registrations, $employees, &$deletedFiles): void {
            foreach ($registrations as $registration) {
                $deletedFiles += $this->deleteRegistrationDocuments($registration);
                $registration->beneficiaries()->delete();
                $registration->delete();
            }

            Employee::query()
                ->whereKey($employees->modelKeys())
                ->delete();
        });

        $this->info(sprintf(
            'Deleted %d registration(s), %d employee(s), and %d document file(s).',
            $registrations->count(),
            $employees->count(),
            $deletedFiles,
        ));

        return self::SUCCESS;
    }

    private function deleteRegistrationDocuments(MedicalRegistration $registration): int
    {
        $disk = RegistrationDocuments::disk();
        $deleted = 0;

        foreach (['family_status_document_path', 'employee_photo_path'] as $attribute) {
            $path = $registration->{$attribute};

            if (filled($path) && $disk->exists($path)) {
                $disk->delete($path);
                $deleted++;
            }
        }

        foreach ($registration->beneficiaries as $beneficiary) {
            $path = $beneficiary->photo_path;

            if (filled($path) && $disk->exists($path)) {
                $disk->delete($path);
                $deleted++;
            }
        }

        return $deleted;
    }
}
