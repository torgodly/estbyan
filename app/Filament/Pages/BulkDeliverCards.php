<?php

namespace App\Filament\Pages;

use App\Enums\CardDeliveryRecipient;
use App\Models\Employee;
use App\Models\User;
use App\Services\DeliveryListProcessor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

/**
 * Paste a list of employee numbers, preview which families can be delivered, then mark them
 * all delivered at once. Uses the same safety rules as `cards:deliver-from-list`.
 *
 * @see docs/cancelled-family-members.md
 *
 * @property-read Schema $form
 */
class BulkDeliverCards extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'تسليم جماعي';

    protected static ?string $title = 'تسليم جماعي بالرقم الوظيفي';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'bulk-deliver-cards';

    protected string $view = 'filament.pages.bulk-deliver-cards';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /**
     * The employee numbers that were previewed; delivery uses exactly these.
     *
     * @var list<string>
     */
    public array $previewedNumbers = [];

    /**
     * @var array{
     *     ready: list<array{row: int, employee_number: string, name: string, workplace: string}>,
     *     blocked: list<array{row: int, employee_number: string, name: string, problem: string}>,
     *     not_found: list<array{row: int, employee_number: string, problem: string}>,
     *     already_delivered: list<array{row: int, employee_number: string, name: string}>,
     *     repeated: list<array{row: int, employee_number: string}>
     * }|null
     */
    public ?array $preview = null;

    /**
     * @var array{count: int, recipient: string}|null
     */
    public ?array $deliveredNotice = null;

    public static function canAccess(): bool
    {
        return User::authenticatedCanAccessFullAdmin();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'الصق الأرقام الوظيفية، اعرض المعاينة، ثم سلّم الكل. لا يُسلَّم إلا من طلبه مقبول وكل بطاقات عائلته مطبوعة ولا توجد عليه حالة مراجعة.';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill([
            'delivered_to' => CardDeliveryRecipient::Administration->value,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('الأرقام الوظيفية')
                ->icon(Heroicon::OutlinedQueueList)
                ->schema([
                    Textarea::make('employee_numbers')
                        ->label('رقم وظيفي في كل سطر (أو مفصولة بفاصلة أو مسافة)')
                        ->placeholder("001433\n002521\n014049")
                        ->rows(8)
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn () => $this->clearPreview()),
                    Radio::make('delivered_to')
                        ->label('سُلّمت البطاقات إلى')
                        ->options(CardDeliveryRecipient::class)
                        ->inline()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->clearPreview()),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('bulk-deliver-form')
                ->livewireSubmitHandler('previewList')
                ->footer([
                    Actions::make([
                        Action::make('previewList')
                            ->label('معاينة')
                            ->icon(Heroicon::OutlinedEye)
                            ->submit('previewList'),
                        $this->deliverAllAction(),
                    ])->key('form-actions'),
                ]),
        ]);
    }

    public function previewList(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->deliveredNotice = null;
        $this->previewedNumbers = self::parseNumbers((string) ($this->form->getState()['employee_numbers'] ?? ''));

        if ($this->previewedNumbers === []) {
            $this->preview = null;

            Notification::make()->title('لم يتم إدخال أي رقم وظيفي')->warning()->send();

            return;
        }

        $this->preview = self::summarize(
            app(DeliveryListProcessor::class)->process($this->rows(), null, $this->recipient(), dryRun: true),
        );
    }

    public function deliverAllAction(): Action
    {
        return Action::make('deliverAll')
            ->label(fn (): string => 'تسليم الكل ('.count($this->preview['ready'] ?? []).')')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (): bool => ($this->preview['ready'] ?? []) !== [])
            ->requiresConfirmation()
            ->modalHeading('تأكيد التسليم')
            ->modalDescription(fn (): string => sprintf(
                'سيتم تسجيل تسليم %d عائلة إلى %s باسمك. لا يمكن التراجع من هذه الصفحة.',
                count($this->preview['ready'] ?? []),
                $this->recipient()->getLabel(),
            ))
            ->modalSubmitActionLabel('نعم، سلّم الكل')
            ->action(fn () => $this->deliverAll());
    }

    public function deliverAll(): void
    {
        abort_unless(static::canAccess(), 403);

        $user = Auth::user();
        assert($user instanceof User);

        if ($this->previewedNumbers === []) {
            return;
        }

        $recipient = $this->recipient();
        $result = app(DeliveryListProcessor::class)->process($this->rows(), $user, $recipient);

        $this->deliveredNotice = [
            'count' => count($result['delivered']),
            'recipient' => $recipient->getLabel(),
        ];
        $this->preview = self::summarize($result);
        $this->preview['delivered'] = $this->preview['ready'];
        $this->preview['ready'] = [];

        Notification::make()
            ->title('تم تسليم '.count($result['delivered']).' عائلة')
            ->body('إلى '.$recipient->getLabel().' · بواسطة '.$user->name)
            ->success()
            ->send();
    }

    public function clearPreview(): void
    {
        $this->preview = null;
        $this->previewedNumbers = [];
        $this->deliveredNotice = null;
    }

    /**
     * Split pasted text into employee numbers exactly as written (leading zeros kept).
     *
     * @return list<string>
     */
    public static function parseNumbers(string $text): array
    {
        return array_values(array_filter(
            preg_split('/[\s,،;]+/u', trim($text)) ?: [],
            fn (string $number): bool => $number !== '',
        ));
    }

    /**
     * @return array<int, array{employee_number: string, name: null}>
     */
    private function rows(): array
    {
        $rows = [];

        foreach ($this->previewedNumbers as $index => $number) {
            $rows[$index + 1] = ['employee_number' => $number, 'name' => null];
        }

        return $rows;
    }

    private function recipient(): CardDeliveryRecipient
    {
        $value = $this->data['delivered_to'] ?? null;

        return $value instanceof CardDeliveryRecipient
            ? $value
            : (CardDeliveryRecipient::tryFrom((string) $value) ?? CardDeliveryRecipient::Administration);
    }

    /**
     * Plain arrays for the view (Livewire cannot keep models in public properties).
     *
     * @param  array<string, list<array<string, mixed>>>  $result
     * @return array<string, list<array<string, mixed>>>
     */
    private static function summarize(array $result): array
    {
        $person = fn (array $item): array => [
            'row' => $item['row'],
            'employee_number' => $item['employee']->employee_number,
            'name' => trim((string) $item['employee']->full_name),
        ];

        return [
            'ready' => array_map(fn (array $item): array => [
                ...$person($item),
                'workplace' => self::workplaceOf($item['employee']),
            ], $result['delivered']),
            'blocked' => array_map(fn (array $item): array => [...$person($item), 'problem' => $item['problem']], $result['blocked']),
            'not_found' => array_map(fn (array $item): array => [
                'row' => $item['row'],
                'employee_number' => $item['employee_number'],
                'problem' => $item['problem'],
            ], $result['not_found']),
            'already_delivered' => array_map($person, $result['already_delivered']),
            'repeated' => $result['repeated'],
        ];
    }

    private static function workplaceOf(Employee $employee): string
    {
        return $employee->workplaceLabel() ?? '—';
    }
}
