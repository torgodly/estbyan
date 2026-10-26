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

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return ['الاسم', 'الإدارة', 'المكتب', 'الحالة'];
    }

    /**
     * @param  Builder<Employee>|Collection<int, Employee>|null  $employees
     * @return list<array{name: string, workplace: string, office: string, status: string}>
     */
    public static function rows(Builder|Collection|null $employees = null): array
    {
        return self::employees($employees)
            ->map(fn (Employee $employee): array => [
                'name' => $employee->full_name ?: '—',
                'workplace' => $employee->workplaceLabel() ?? '—',
                'office' => $employee->officeLabel() ?? '—',
                'status' => self::STATUS,
            ])
            ->values()
            ->all();
    }

    public static function binary(Builder|Collection|null $employees = null): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(11);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('العائلات المطبوعة');
        $sheet->setRightToLeft(true);

        foreach (self::headings() as $index => $heading) {
            $sheet->setCellValueExplicit(chr(ord('A') + $index).'1', $heading, DataType::TYPE_STRING);
        }

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);
        $sheet->getStyle('A1:D1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('1E3A5F');
        $sheet->getStyle('A1:D1')->getFont()->getColor()->setRGB('FFFFFF');

        $rowNumber = 2;

        foreach (self::rows($employees) as $row) {
            $sheet->setCellValueExplicit('A'.$rowNumber, $row['name'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$rowNumber, $row['workplace'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$rowNumber, $row['office'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$rowNumber, $row['status'], DataType::TYPE_STRING);
            $rowNumber++;
        }

        $lastRow = max(1, $rowNumber - 1);
        $sheet->getStyle('A1:A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('B1:D'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(24);
        $sheet->getColumnDimension('C')->setWidth(24);
        $sheet->getColumnDimension('D')->setWidth(16);

        $writer = new Xlsx($spreadsheet);

        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    public static function download(Builder|Collection|null $employees = null): StreamedResponse
    {
        $binary = self::binary($employees);

        return response()->streamDownload(
            function () use ($binary): void {
                echo $binary;
            },
            self::FILENAME,
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
