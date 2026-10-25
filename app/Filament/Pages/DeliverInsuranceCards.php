<?php

namespace App\Filament\Pages;

use App\Enums\CardDeliveryRecipient;
use App\Models\Employee;
use App\Models\User;
use App\Services\InsuranceCardScanService;
use App\Support\InsuranceCardNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class DeliverInsuranceCards extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandRaised;

    protected static ?string $navigationLabel = 'تسليم البطاقات';

    protected static ?string $title = 'تسليم بطاقات التأمين';

    protected static string|UnitEnum|null $navigationGroup = 'التسجيل الطبي';

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'deliver-insurance-cards';

    protected string $view = 'filament.pages.deliver-insurance-cards';

    protected Width|string|null $maxContentWidth = Width::FourExtraLarge;

    /**
     * @var array<string, mixed>
     */
    protected array $extraBodyAttributes = [
        'class' => 'hr-deliver-page',
    ];

    public string $scan = '';

    /**
     * @var list<array<string, mixed>>
     */
    public array $scanned = [];

    /**
     * @var array{name: string, recipient: string}|null
     */
    public ?array $deliveryNotice = null;

    public function mount(): void
    {
        $notice = session()->pull('deliveryNotice');

        if (is_array($notice) && isset($notice['name'], $notice['recipient'])) {
            $this->deliveryNotice = [
                'name' => (string) $notice['name'],
                'recipient' => (string) $notice['recipient'],
            ];
        }
    }

    public static function canAccess(): bool
    {
        return User::authenticatedCanDeliverInsuranceCards();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'تسليم عائلة واحدة في كل مرة. يجب مسح بطاقة الموظف وكل أفراد العائلة بما فيهم الأب والأم.';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function groups(): array
    {
        return app(InsuranceCardScanService::class)->groups($this->scanned);
    }

    public function family(): ?array
    {
        return $this->groups()[0] ?? null;
    }

    public function canDeliver(): bool
    {
        $family = $this->family();

        return $family !== null
            && $family['complete'] === true
            && ($family['is_delivered'] ?? false) === false;
    }

    public function scanCard(): void
    {
        abort_unless(static::canAccess(), 403);

        $raw = trim($this->scan);
        $this->reset('scan');
        $this->dispatch('insurance-card-scanned');

        if (InsuranceCardNumber::normalize($raw) === null) {
            Notification::make()
                ->title('رقم البطاقة غير صالح')
                ->body('امسح باركود البطاقة أو أدخل الرقم المكوّن من 8 أرقام.')
                ->danger()
                ->send();

            return;
        }

        $hit = app(InsuranceCardScanService::class)->find($raw);

        if ($hit === null) {
            Notification::make()
                ->title('لم يتم العثور على البطاقة')
                ->danger()
                ->send();

            return;
        }

        $activeEmployeeId = $this->activeEmployeeId();

        if ($activeEmployeeId !== null && $hit->employeeId !== $activeEmployeeId) {
            Notification::make()
                ->title('أنهِ تسليم هذه العائلة أولاً')
                ->body('امسح بقية بطاقات هذه العائلة ثم اضغط تم التسليم، أو أفرغ القائمة للبدء من جديد.')
                ->warning()
                ->send();

            return;
        }

        $existingIndex = collect($this->scanned)->search(
            fn (array $item): bool => $item['card_number'] === $hit->cardNumber,
        );

        if ($existingIndex !== false) {
            return;
        }

        $this->scanned[] = $hit->toArray();
        $this->deliveryNotice = null;
    }

    public function removeCard(string $cardNumber): void
    {
        abort_unless(static::canAccess(), 403);

        $this->scanned = collect($this->scanned)
            ->reject(fn (array $hit): bool => $hit['card_number'] === $cardNumber)
            ->values()
            ->all();
    }

    public function clearScanned(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->scanned = [];
    }

    public function markDelivered(string|CardDeliveryRecipient $deliveredTo): void
    {
        abort_unless(static::canAccess(), 403);

        $family = $this->family();

        if (($family['is_delivered'] ?? false) === true) {
            Notification::make()
                ->title('تم التسليم مسبقاً — للعرض فقط')
                ->body('لا يمكن تعديل أو إعادة تسليم بطاقات موظف مُسلَّم.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->canDeliver()) {
            Notification::make()
                ->title('لا يمكن التسليم قبل مسح كل البطاقات')
                ->body('يجب مسح بطاقة الموظف وكل أفراد العائلة بما فيهم الأب والأم.')
                ->warning()
                ->send();

            return;
        }

        $recipient = $deliveredTo instanceof CardDeliveryRecipient
            ? $deliveredTo
            : CardDeliveryRecipient::tryFrom($deliveredTo);

        if ($recipient === null) {
            Notification::make()
                ->title('حدّد إلى من سُلّمت البطاقات')
                ->danger()
                ->send();

            return;
        }

        $user = Auth::user();
        assert($user instanceof User);

        $employee = Employee::query()->find($family['employee_id'] ?? null);

        if ($employee === null) {
            Notification::make()
                ->title('لم يتم العثور على الموظف')
                ->danger()
                ->send();

            return;
        }

        if ($employee->cardsAreDelivered()) {
            Notification::make()
                ->title('تم التسليم مسبقاً — للعرض فقط')
                ->body('لا يمكن تعديل أو إعادة تسليم بطاقات موظف مُسلَّم.')
                ->warning()
                ->send();

            return;
        }

        app(InsuranceCardScanService::class)->markPrinted($this->scanned);
        $employee->markCardsDelivered($user, $recipient);

        $this->reset(['scan', 'scanned']);
        $this->deliveryNotice = [
            'name' => $employee->full_name,
            'recipient' => $recipient->getLabel(),
        ];

        session()->flash('deliveryNotice', $this->deliveryNotice);

        Notification::make()
            ->title('تم تسليم بطاقات الموظف '.$employee->full_name.' بنجاح')
            ->body('إلى '.$recipient->getLabel().' · بواسطة '.$user->name)
            ->success()
            ->send();

        // Full navigation: modal actions can leave the scan UI stuck even after
        // clearing state, so delivery staff had to reopen the page manually.
        $this->redirect(static::getUrl());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markDelivered')
                ->label('تم التسليم')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->disabled(fn (): bool => ! $this->canDeliver())
                ->modalHeading('تأكيد التسليم')
                ->modalDescription(null)
                ->modalWidth(Width::Large)
                ->extraModalWindowAttributes(['class' => 'hr-deliver-modal-window'])
                ->fillForm(fn (): array => [
                    'delivered_to' => $this->family()['delivered_to'] ?? null,
                ])
                ->schema([
                    ViewField::make('delivered_to')
                        ->hiddenLabel()
                        ->view('filament.pages.partials.deliver-cards-modal')
                        ->viewData(fn (): array => [
                            'family' => $this->family() ?? [],
                        ])
                        ->required()
                        ->live(),
                ])
                ->modalSubmitActionLabel('تأكيد التسليم')
                ->modalCancelActionLabel('إلغاء')
                ->action(function (array $data): void {
                    $deliveredTo = $data['delivered_to'] ?? '';

                    $this->markDelivered(
                        $deliveredTo instanceof CardDeliveryRecipient
                            ? $deliveredTo
                            : (string) $deliveredTo,
                    );
                }),
            Action::make('clearScanned')
                ->label('إفراغ القائمة')
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->disabled(fn (): bool => $this->scanned === [])
                ->action(function (): void {
                    $this->clearScanned();
                }),
        ];
    }

    private function activeEmployeeId(): ?int
    {
        $employeeId = $this->scanned[0]['employee_id'] ?? null;

        return $employeeId === null ? null : (int) $employeeId;
    }
}
