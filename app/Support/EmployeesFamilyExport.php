<?php

namespace App\Support;

use App\Enums\BeneficiaryRelationship;
use App\Models\Beneficiary;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeesFamilyExport
{
    public const FILENAME = 'الموظفون-والعائلة.xlsx';

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return ['الاسم', 'الرقم الوطني', 'النوع', 'صلة القرابة'];
    }

    /**
     * @param  EloquentCollection<int, Employee>|Collection<int, Employee>|null  $employees
     * @return list<array{name: string, national_id: string, type: string, relationship: string, is_employee: bool}>
     */
    public function rows(EloquentCollection|Collection|null $employees = null): array
    {
        $employees = $this->employees($employees);
        $rows = [];

        foreach ($employees as $employee) {
            $rows[] = [
                'name' => $employee->full_name,
                'national_id' => $this->identity($employee->national_id),
                'type' => 'موظف',
                'relationship' => '—',
                'is_employee' => true,
            ];

            $registration = $employee->latestSubmittedRegistration;
            $gender = $registration?->gender;

            foreach ($this->sortedBeneficiaries($registration?->beneficiaries ?? collect()) as $beneficiary) {
                $rows[] = [
                    'name' => $beneficiary->full_name,
                    'national_id' => $this->identity($beneficiary->national_id),
                    'type' => 'فرد عائلة',
                    'relationship' => $beneficiary->relationship?->label($gender) ?? '—',
                    'is_employee' => false,
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  EloquentCollection<int, Employee>|Collection<int, Employee>|null  $employees
     */
    public function spreadsheet(EloquentCollection|Collection|null $employees = null): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(11);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('الموظفون والعائلة');
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');
        $sheet->getDefaultRowDimension()->setRowHeight(18);

        $this->writeHeadingRow($sheet);
        $this->applyColumnLayout($sheet);

        $rowNumber = 2;

        foreach ($this->rows($employees) as $row) {
            $sheet->setCellValueExplicit('A'.$rowNumber, $row['name'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$rowNumber, $row['national_id'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$rowNumber, $row['type'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$rowNumber, $row['relationship'], DataType::TYPE_STRING);

            if ($row['is_employee']) {
                $sheet->getStyle('A'.$rowNumber.':D'.$rowNumber)->getFont()->setBold(true);
                $sheet->getStyle('A'.$rowNumber.':D'.$rowNumber)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('EEF2FF');
            }

            $rowNumber++;
        }

        $lastRow = max(1, $rowNumber - 1);
        $sheet->getStyle('A1:A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('B1:D'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return $spreadsheet;
    }

    /**
     * @param  EloquentCollection<int, Employee>|Collection<int, Employee>|null  $employees
     */
    public function download(EloquentCollection|Collection|null $employees = null): StreamedResponse
    {
        $spreadsheet = $this->spreadsheet($employees);

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                (new Xlsx($spreadsheet))->save('php://output');
            },
            self::FILENAME,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    /**
     * @param  EloquentCollection<int, Employee>|Collection<int, Employee>|null  $employees
     * @return EloquentCollection<int, Employee>|Collection<int, Employee>
     */
    protected function employees(EloquentCollection|Collection|null $employees): EloquentCollection|Collection
    {
        if ($employees === null) {
            return Employee::query()
                ->with(['latestSubmittedRegistration.beneficiaries'])
                ->orderBy('full_name')
                ->get();
        }

        $employees->loadMissing(['latestSubmittedRegistration.beneficiaries']);

        return $employees;
    }

    /**
     * @param  Collection<int, Beneficiary>  $beneficiaries
     * @return Collection<int, Beneficiary>
     */
    protected function sortedBeneficiaries(Collection $beneficiaries): Collection
    {
        $order = [
            BeneficiaryRelationship::Spouse->value => 1,
            BeneficiaryRelationship::Son->value => 2,
            BeneficiaryRelationship::Daughter->value => 3,
            BeneficiaryRelationship::Father->value => 4,
            BeneficiaryRelationship::Mother->value => 5,
        ];

        return $beneficiaries
            ->sortBy([
                fn (Beneficiary $beneficiary): int => $order[$beneficiary->relationship?->value] ?? 99,
                fn (Beneficiary $beneficiary): string => $beneficiary->full_name,
            ])
            ->values();
    }

    protected function identity(?string $nationalId): string
    {
        return filled($nationalId) ? (string) $nationalId : '—';
    }

    protected function writeHeadingRow(Worksheet $sheet): void
    {
        foreach (self::headings() as $index => $heading) {
            $sheet->setCellValueExplicit(
                chr(ord('A') + $index).'1',
                $heading,
                DataType::TYPE_STRING,
            );
        }

        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('1E3A5F');
        $sheet->getStyle('A1:D1')->getFont()->getColor()->setRGB('FFFFFF');
    }

    protected function applyColumnLayout(Worksheet $sheet): void
    {
        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(16);
    }
}
