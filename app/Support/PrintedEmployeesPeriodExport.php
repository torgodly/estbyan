<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\MedicalRegistration;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintedEmployeesPeriodExport
{
    public const STATUS = 'تمت الطباعه';

    public const TIMEZONE = 'Africa/Tripoli';

    public static function placeLabel(): string
    {
        return 'الإدارة';
    }

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return ['الاسم', 'الإدارة', 'المكتب', 'الحالة'];
    }

    /**
     * @return Builder<Employee>
     */
    public static function query(CarbonInterface|string $from, CarbonInterface|string $until): Builder
    {
        [$start, $end] = self::periodBounds($from, $until);

        return Employee::query()
            ->whereNotNull('card_printed_at')
            ->whereBetween('card_printed_at', [$start, $end])
            ->whereDoesntHave(
                'medicalRegistrations.beneficiaries',
                fn (Builder $query) => $query->whereNull('card_printed_at'),
            )
            ->with([
                'latestSubmittedRegistration.beneficiaries',
                'latestMedicalRegistration.beneficiaries',
            ])
            ->orderBy('full_name')
            ->orderBy('id');
    }

    /**
     * @return list<array{name: string, workplace: string, office: string, status: string}>
     */
    public static function rows(CarbonInterface|string $from, CarbonInterface|string $until): array
    {
        return self::query($from, $until)
            ->get()
            ->map(function (Employee $employee): array {
                $registration = $employee->latestSubmittedRegistration
                    ?? $employee->latestMedicalRegistration;

                return [
                    'name' => $employee->full_name ?: ($registration?->full_name ?: '—'),
                    'workplace' => $employee->workplaceLabel()
                        ?? $registration?->workplaceLabel()
                        ?? '—',
                    'office' => $employee->officeLabel() ?? '—',
                    'status' => self::STATUS,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{name: string, workplace: string, cards: int}>
     */
    public static function slips(CarbonInterface|string $from, CarbonInterface|string $until): array
    {
        return self::query($from, $until)
            ->get()
            ->map(function (Employee $employee): array {
                $registration = $employee->latestSubmittedRegistration
                    ?? $employee->latestMedicalRegistration;

                return [
                    'name' => $employee->full_name ?: ($registration?->full_name ?: '—'),
                    'workplace' => $employee->workplaceLabel()
                        ?? $registration?->workplaceLabel()
                        ?? '—',
                    'cards' => 1 + self::countedFamilyMembers($registration),
                ];
            })
            ->values()
            ->all();
    }

    private static function countedFamilyMembers(?MedicalRegistration $registration): int
    {
        if ($registration === null) {
            return 0;
        }

        return $registration->beneficiaries->count();
    }

    public static function binary(CarbonInterface|string $from, CarbonInterface|string $until): string
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getDefaultStyle()->getFont()->setName('Tahoma')->setSize(11);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('البطاقات المطبوعة');
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

        foreach (self::rows($from, $until) as $row) {
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

    public static function download(CarbonInterface|string $from, CarbonInterface|string $until): StreamedResponse
    {
        $binary = self::binary($from, $until);
        $fromLabel = Carbon::parse($from, self::TIMEZONE)->toDateString();
        $untilLabel = Carbon::parse($until, self::TIMEZONE)->toDateString();
        $filename = "البطاقات-المطبوعة-{$fromLabel}-إلى-{$untilLabel}.xlsx";

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
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodBounds(CarbonInterface|string $from, CarbonInterface|string $until): array
    {
        $start = Carbon::parse($from, self::TIMEZONE)->startOfDay()->utc();
        $end = Carbon::parse($until, self::TIMEZONE)->endOfDay()->utc();

        return [$start, $end];
    }
}
