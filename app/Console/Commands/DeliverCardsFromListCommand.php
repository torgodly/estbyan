<?php

namespace App\Console\Commands;

use App\Enums\CardDeliveryRecipient;
use App\Models\Employee;
use App\Models\User;
use App\Services\DeliveryListProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

#[Signature('cards:deliver-from-list
    {file : Excel file (path, or just its name inside database/data/delivery-lists) with a "الرقم الوظيفي" or "الاسم" column}
    {--to=administration : Who received the cards: administration or employee}
    {--by= : Email of the user recording the delivery (asked when missing)}
    {--dry-run : Show what would happen without changing data}
    {--force : Skip the confirmation prompt}')]
#[Description('Mark families as delivered from an Excel list of employee numbers (or exact, unique names). Only certain matches with every card printed are marked.')]
class DeliverCardsFromListCommand extends Command
{
    private const NUMBER_HEADING = 'الرقم الوظيفي';

    private const NAME_HEADING = 'الاسم';

    private const LISTS_FOLDER = 'database/data/delivery-lists';

    /**
     * Names from a name-only list that could not be matched to exactly one employee.
     *
     * @var list<array{row: int, name: string, problem: string}>
     */
    private array $uncertainNames = [];

    public function handle(DeliveryListProcessor $processor): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $recipient = CardDeliveryRecipient::tryFrom((string) $this->option('to'));

        if ($recipient === null) {
            $this->components->error('--to must be administration or employee.');

            return self::FAILURE;
        }

        $rows = $this->readRows((string) $this->argument('file'), $processor);

        if ($rows === null) {
            return self::FAILURE;
        }

        $preview = $processor->process($rows, null, $recipient, dryRun: true);
        $this->report($preview, dryRun: true);

        if ($dryRun || $preview['delivered'] === []) {
            return self::SUCCESS;
        }

        $user = $this->deliveringUser();

        if ($user === null) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(sprintf('Mark %d famil(ies) as delivered to %s, recorded by %s?', count($preview['delivered']), $recipient->getLabel(), $user->name), false)) {
            $this->components->warn('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        $result = $processor->process($rows, $user, $recipient);

        $this->components->info(sprintf('Delivered %d famil(ies).', count($result['delivered'])));

        return self::SUCCESS;
    }

    /**
     * The user recorded as "delivered by": --by, or picked from the list when running by hand.
     */
    private function deliveringUser(): ?User
    {
        $email = (string) $this->option('by');

        if ($email === '' && $this->input->isInteractive()) {
            $email = (string) $this->choice(
                'Who is recording this delivery?',
                User::query()->orderBy('name')->pluck('email')->all(),
            );
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->components->error('Pass --by=<email> of an existing user.');
        }

        return $user;
    }

    /**
     * @return array<int, array{employee_number: string, name: ?string}>|null
     */
    private function readRows(string $path, DeliveryListProcessor $processor): ?array
    {
        if (! is_file($path) && is_file(base_path(self::LISTS_FOLDER.'/'.$path))) {
            $path = base_path(self::LISTS_FOLDER.'/'.$path);
        }

        if (! is_file($path)) {
            $this->components->error("File not found: {$path}");

            return null;
        }

        $sheetRows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, true);
        $header = array_map(fn (mixed $value): string => trim((string) $value), (array) reset($sheetRows));
        $headerRow = (int) array_key_first($sheetRows);
        $numberColumn = array_search(self::NUMBER_HEADING, $header, true);
        $nameColumn = array_search(self::NAME_HEADING, $header, true);

        if ($numberColumn === false && $nameColumn === false) {
            $this->components->error('The first row must have a "'.self::NUMBER_HEADING.'" or "'.self::NAME_HEADING.'" column.');

            return null;
        }

        if ($numberColumn === false) {
            $this->components->warn('No "'.self::NUMBER_HEADING.'" column: matching by exact, unique name only.');

            $names = [];

            foreach ($sheetRows as $rowNumber => $cells) {
                $name = $this->cellText($cells[$nameColumn] ?? null);

                if ((int) $rowNumber !== $headerRow && $name !== '') {
                    $names[(int) $rowNumber] = $name;
                }
            }

            ['rows' => $rows, 'uncertain' => $this->uncertainNames] = $processor->resolveNames($names);

            return $rows;
        }

        $rows = [];

        foreach ($sheetRows as $rowNumber => $cells) {
            if ((int) $rowNumber === $headerRow) {
                continue;
            }

            $number = $this->cellText($cells[$numberColumn] ?? null);

            if ($number === '') {
                continue;
            }

            $rows[(int) $rowNumber] = [
                'employee_number' => $number,
                'name' => $nameColumn !== false ? $this->cellText($cells[$nameColumn] ?? null) : null,
            ];
        }

        if ($rows === []) {
            $this->components->error('No employee numbers found in the file.');

            return null;
        }

        return $rows;
    }

    /**
     * Read a cell as text without adding or removing anything (no ".0", no padding).
     */
    private function cellText(mixed $value): string
    {
        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $result
     */
    private function report(array $result, bool $dryRun): void
    {
        $label = fn (Employee $employee): string => $employee->employee_number.' '.$employee->full_name;

        if ($result['blocked'] !== []) {
            $this->components->warn('Will NOT be marked (fix first):');
            $this->table(['Row', 'Employee', 'Problem'], collect($result['blocked'])->map(fn (array $item): array => [
                $item['row'], $label($item['employee']), $item['problem'],
            ])->all());
        }

        if ($this->uncertainNames !== []) {
            $this->components->warn('Name not certain, will NOT be marked:');
            $this->table(['Row', 'Name in list', 'Problem'], collect($this->uncertainNames)->map(fn (array $item): array => [
                $item['row'], $item['name'], $item['problem'],
            ])->all());
        }

        if ($result['not_found'] !== []) {
            $this->components->warn('Employee number not found:');
            $this->table(['Row', 'Employee number', 'Problem'], collect($result['not_found'])->map(fn (array $item): array => [
                $item['row'], $item['employee_number'], $item['problem'],
            ])->all());
        }

        if ($result['repeated'] !== []) {
            $this->components->info('Repeated in the file (counted once): rows '.collect($result['repeated'])->pluck('row')->implode(', '));
        }

        $this->components->info(sprintf(
            '%s %d famil(ies). Already delivered: %d. Blocked: %d. Not found: %d. Uncertain names: %d.',
            $dryRun ? 'Ready to mark' : 'Marked',
            count($result['delivered']),
            count($result['already_delivered']),
            count($result['blocked']),
            count($result['not_found']),
            count($this->uncertainNames),
        ));
    }
}
