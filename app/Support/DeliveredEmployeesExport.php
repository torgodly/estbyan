<?php

namespace App\Support;

use App\Enums\CardDeliveryRecipient;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DeliveredEmployeesExport
{
    public const FILENAME = 'العائلات-المسلمة.xlsx';

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return [
            'الاسم',
            'الرقم الوظيفي',
            'رقم البطاقة',
            'الرقم الوطني',
            'الإدارة',
            'المكتب',
            'عدد البطاقات',
            'جهة الاستلام',
            'بواسطة',
            'تاريخ التسليم',
            'المرجع',
        ];
    }

    /**
     * @param  Builder<Employee>|Collection<int, Employee>|null  $employees
     * @return list<array{
     *     name: string,
     *     employee_number: string,
     *     card_number: string,
     *     national_id: string,
     *     workplace: string,
     *     office: string,
     *     family_cards: int,
     *     recipient: string,
     *     delivered_by: string,
     *     delivered_at: string,
     *     reference: string
     * }>
     */
    public static function rows(Builder|Collection|null $employees = null): array
    {
        $records = self::employees($employees);

        return $records
            ->map(function (Employee $employee): array {
                $registration = $employee->latestSubmittedRegistration;

                return [
                    'name' => $employee->full_name ?: '—',
                    'employee_number' => (string) ($employee->employee_number ?: '—'),
                    'card_number' => InsuranceCardNumber::display($employee->card_number),
                    'national_id' => (string) ($employee->national_id ?: '—'),
                    'workplace' => $employee->workplaceLabel() ?? '—',
                    'office' => $employee->officeLabel() ?? '—',
                    'family_cards' => $employee->familyCardsCount(),
                    'recipient' => $employee->cards_delivered_to instanceof CardDeliveryRecipient
                        ? $employee->cards_delivered_to->getLabel()
                        : '—',
                    'delivered_by' => $employee->cardsDeliveredBy?->name ?: '—',
                    'delivered_at' => $employee->cards_delivered_at
                        ?->timezone(PrintedEmployeesPeriodExport::TIMEZONE)
                        ->format('Y-m-d H:i') ?? '—',
                    'reference' => (string) ($registration?->reference_number ?: '—'),
                ];
            })
            ->values()
            ->all();
    }

    public static function binary(Builder|Collection|null $employees = null): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(11);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('العائلات المسلمة');
        $sheet->setRightToLeft(true);

        foreach (self::headings() as $index => $heading) {
            $sheet->setCellValueExplicit(chr(ord('A') + $index).'1', $heading, DataType::TYPE_STRING);
        }

        $lastColumn = chr(ord('A') + count(self::headings()) - 1);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setRGB('1E3A5F');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->getColor()->setRGB('FFFFFF');

        $rowNumber = 2;

        foreach (self::rows($employees) as $row) {
            $sheet->setCellValueExplicit('A'.$rowNumber, $row['name'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B'.$rowNumber, $row['employee_number'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C'.$rowNumber, $row['card_number'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D'.$rowNumber, $row['national_id'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E'.$rowNumber, $row['workplace'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F'.$rowNumber, $row['office'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('G'.$rowNumber, (string) $row['family_cards'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('H'.$rowNumber, $row['recipient'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('I'.$rowNumber, $row['delivered_by'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('J'.$rowNumber, $row['delivered_at'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('K'.$rowNumber, $row['reference'], DataType::TYPE_STRING);
            $rowNumber++;
        }

        $lastRow = max(1, $rowNumber - 1);
        $sheet->getStyle('A1:A'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('B1:'.$lastColumn.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getColumnDimension('A')->setWidth(36);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(16);
        $sheet->getColumnDimension('D')->setWidth(16);
        $sheet->getColumnDimension('E')->setWidth(24);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(14);
        $sheet->getColumnDimension('I')->setWidth(22);
        $sheet->getColumnDimension('J')->setWidth(18);
        $sheet->getColumnDimension('K')->setWidth(18);

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
            : Employee::query()->cardsDelivered();

        return $query
            ->with([
                'cardsDeliveredBy',
                'latestSubmittedRegistration' => fn ($registrationQuery) => $registrationQuery->withCount('beneficiaries'),
            ])
            ->reorder()
            ->orderByDesc('cards_delivered_at')
            ->orderBy('full_name')
            ->get();
    }
}
