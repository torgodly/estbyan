<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FullyPrintedEmployeesExport
{
    public const STATUS = 'تمت الطباعه';

    public const FILENAME = 'العائلات-المطبوعة-بالكامل.xlsx';

    public const NOT_DELIVERED_STATUS = 'مطبوعة ولم تُسلَّم';

    public const NOT_DELIVERED_FILENAME = 'العائلات-المطبوعة-غير-المسلمة.xlsx';

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return ['الرقم الوظيفي', 'الاسم', 'الإدارة', 'المكتب', 'الحالة'];
    }

    /**
     * @param  Builder<Employee>|Collection<int, Employee>|null  $employees
     *                                                                       The employee number identifies each family, so the sheet can be matched back later
     *                                                                       (e.g. to mark the families as delivered).
     * @return list<array{employee_number: string, name: string, workplace: string, office: string, status: string}>
     */
    public static function rows(Builder|Collection|null $employees = null, string $status = self::STATUS): array
    {
        return self::employees($employees)
            ->map(fn (Employee $employee): array => [
                'employee_number' => (string) $employee->employee_number,
                'name' => $employee->full_name ?: '—',
                'workplace' => $employee->workplaceLabel() ?? '—',
                'office' => $employee->officeLabel() ?? '—',
                'status' => $status,
            ])
            ->values()
            ->all();
    }

    public static function binary(Builder|Collection|null $employees = null, string $status = self::STATUS): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(11);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('العائلات المطبوعة');
        $sheet->setRightToLeft(true);

        foreach (self::headings() as $index => $heading) {
            $sheet->setCellValueExplicit(chr(ord('A') + $index).'1', $heading, DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A1:E1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('1E3A5F');
        $sheet->getStyle('A1:E1')->getFont()->getColor()->setRGB('FFFFFF');

        $rowNumber = 2;

        foreach (self::rows($employees, $status) as $row) {
            $sheet->setCellValueExplicit('A'.$rowNumber, $row['employee_number'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$rowNumber, $row['name'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$rowNumber, $row['workplace'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$rowNumber, $row['office'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E'.$rowNumber, $row['status'], DataType::TYPE_STRING);
            $rowNumber++;
        }

        $lastRow = max(1, $rowNumber - 1);
        $sheet->getStyle('A1:A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('B1:B'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('C1:E'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(16);
        $sheet->getColumnDimension('B')->setWidth(36);
        $sheet->getColumnDimension('C')->setWidth(24);
        $sheet->getColumnDimension('D')->setWidth(24);
        $sheet->getColumnDimension('E')->setWidth(16);

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    public static function download(
        Builder|Collection|null $employees = null,
        string $status = self::STATUS,
        string $filename = self::FILENAME,
    ): StreamedResponse {
        $binary = self::binary($employees, $status);

        return response()->streamDownload(
            function () use ($binary): void {
                echo $binary;
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }

    /**
     * @param  Builder<Employee>|Collection<int, Employee>|null  $employees
     * @return Collection<int, Employee>
     */
    private static function employees(Builder|Collection|null $employees): Collection
    {
        if ($employees instanceof Collection) {
            return $employees->values();
        }

        $query = $employees instanceof Builder
            ? clone $employees
            : Employee::query()->cardsFullyPrinted();

        return $query
            ->reorder()
            ->orderByDesc('card_printed_at')
            ->orderBy('full_name')
            ->get();
    }
}
