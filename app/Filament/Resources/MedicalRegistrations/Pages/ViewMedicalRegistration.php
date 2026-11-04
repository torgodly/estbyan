<?php

namespace App\Filament\Resources\MedicalRegistrations\Pages;

use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;
use App\Filament\Resources\PendingReviews\PendingReviewResource;
use App\Models\Beneficiary;
use App\Models\User;
use App\Services\FamilyMemberDeleter;
use App\Services\InsuranceCardPrintMarker;
use App\Services\ReferenceCardGenerator;
use App\Services\RegistrationReviewService;
use App\Support\EmployeeInsuranceCard;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewMedicalRegistration extends ViewRecord
{
    protected static string $resource = MedicalRegistrationResource::class;

    protected string $view = 'filament.resources.medical-registrations.pages.view-registration';

    public function getTitle(): string|Htmlable
    {
        return $this->record->full_name ?: 'ملف الطلب';
    }

    public function getHeading(): string|Htmlable
    {
        return $this->record->reference_number
            ? 'ملف الطلب '.$this->record->reference_number
            : 'ملف الطلب';
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['employee', 'beneficiaries', 'reviewer', 'reviewLogs.user']);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getHeaderActions(): array
    {
        $review = app(RegistrationReviewService::class);

        return [
            Action::make('approve')
                ->label('اعتماد')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->visible(fn (): bool => $review->canApprove($this->record))
                ->requiresConfirmation()
                ->modalHeading('اعتماد الطلب')
                ->modalDescription('سيُقفل الطلب أمام الموظف بعد الاعتماد.')
                ->schema([
                    Textarea::make('review_note')
                        ->label('ملاحظة (اختياري)')
                        ->rows(3),
                ])
                ->action(function (array $data) use ($review): void {
                    $reviewer = Auth::user();
                    assert($reviewer instanceof User);

                    $review->approve(
                        $this->record,
                        $reviewer,
                        $data['review_note'] ?? null,
                    );

                    Notification::make()->title('تم اعتماد الطلب')->success()->send();

                    if ($this->shouldReturnToReviewQueue()) {
                        $this->redirect(PendingReviewResource::getUrl());

                        return;
                    }

                    $this->record->refresh()->loadMissing(['employee', 'beneficiaries', 'reviewer', 'reviewLogs.user']);
                }),
            Action::make('decline')
                ->label('رفض')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->visible(fn (): bool => $review->canDecline($this->record))
                ->requiresConfirmation()
                ->modalHeading('رفض الطلب')
                ->modalDescription('سيتمكن الموظف من تعديل الطلب وإعادة إرساله.')
                ->schema([
                    Textarea::make('review_note')
                        ->label('سبب الرفض')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data) use ($review): void {
                    $reviewer = Auth::user();
                    assert($reviewer instanceof User);

                    $review->decline(
                        $this->record,
                        $reviewer,
                        (string) ($data['review_note'] ?? ''),
                    );

                    Notification::make()->title('تم رفض الطلب')->danger()->send();

                    if ($this->shouldReturnToReviewQueue()) {
                        $this->redirect(PendingReviewResource::getUrl());

                        return;
                    }

                    $this->record->refresh()->loadMissing(['employee', 'beneficiaries', 'reviewer', 'reviewLogs.user']);
                }),
            Action::make('viewEmployee')
                ->label('ملف الموظف')
                ->icon('heroicon-o-user')
                ->color('gray')
                ->url(fn (): ?string => $this->record->employee_id
                    ? EmployeeResource::getUrl('view', ['record' => $this->record->employee_id])
                    : null)
                ->visible(fn (): bool => filled($this->record->employee_id) && $this->canAccessStaffRecordLinks()),
            Action::make('downloadInsuranceCards')
                ->label('تحميل PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => $this->canManageInsuranceCards())
                ->action(fn (): mixed => $this->js('window.exportInsuranceCards("download")')),
            Action::make('printInsuranceCards')
                ->label('طباعة')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (): bool => $this->canManageInsuranceCards())
                ->action(fn (): mixed => $this->js('window.exportInsuranceCards("print")')),
            Action::make('downloadReferenceCard')
                ->label('تحميل بطاقة المراجعة')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (): bool => filled($this->record->reference_number) && $this->canAccessStaffRecordLinks())
                ->action(function (ReferenceCardGenerator $generator): StreamedResponse {
                    $png = $generator->png($this->record);
                    $filename = 'tax-'.$this->record->reference_number.'.png';

                    return response()->streamDownload(
                        function () use ($png): void {
                            echo $png;
                        },
                        $filename,
                        ['Content-Type' => 'image/png'],
                    );
                }),
        ];
    }

    /**
     * Support-only: permanently delete one family member with their photo.
     * Rendered per family member in the view with `['beneficiary' => id]`.
     */
    public function deleteFamilyMemberAction(): Action
    {
        return Action::make('deleteFamilyMember')
            ->label('حذف نهائي')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->size(Size::Small)
            ->visible(fn (): bool => $this->canDeleteFamilyMembers())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'حذف '.($this->familyMemberFor($arguments)?->full_name ?? 'فرد العائلة').' نهائياً')
            ->modalDescription('سيُحذف فرد العائلة وصورته وبطاقته بشكل نهائي. لا يمكن التراجع عن ذلك.')
            ->modalSubmitActionLabel('نعم، احذف نهائياً')
            ->action(function (array $arguments, FamilyMemberDeleter $deleter): void {
                abort_unless($this->canDeleteFamilyMembers(), 403);

                $familyMember = $this->familyMemberFor($arguments);
                abort_if($familyMember === null, 404);

                $result = $deleter->delete($familyMember->id);

                Notification::make()
                    ->title('تم حذف '.$result['name'].' نهائياً')
                    ->success()
                    ->send();

                $this->refreshInsuranceCardRecords();
            });
    }

    public function canDeleteFamilyMembers(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->isSmartCare();
    }

    /**
     * Only family members of this registration can be targeted.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function familyMemberFor(array $arguments): ?Beneficiary
    {
        return Beneficiary::queryWithCancelled()
            ->where('medical_registration_id', $this->getRecord()->getKey())
            ->find($arguments['beneficiary'] ?? null);
    }

    public function canManageInsuranceCards(): bool
    {
        $user = Auth::user();

        return $user instanceof User && $user->canManageInsuranceCards();
    }

    public function canAccessStaffRecordLinks(): bool
    {
        return User::authenticatedCanAccessFullAdmin();
    }

    protected function shouldReturnToReviewQueue(): bool
    {
        return User::authenticatedIsReviewer();
    }

    public function insuranceCardPrintHtml(?string $personKey = null): string
    {
        abort_unless($this->canManageInsuranceCards(), 403);

        $cards = $this->insuranceCards();

        if (filled($personKey)) {
            $cards = $cards
                ->where('personKey', $personKey)
                ->values();
        }

        abort_if($cards->isEmpty(), 404);

        return view('cards.employee-insurance-card', [
            'cards' => $cards,
            'embedAssets' => false,
            'preview' => false,
            'printPack' => true,
        ])->render();
    }

    /**
     * @return Collection<int, EmployeeInsuranceCard>
     */
    public function insuranceCards(): Collection
    {
        return EmployeeInsuranceCard::collection($this->getRecord());
    }

    public function toggleInsuranceCardPrinted(string $personKey): void
    {
        abort_unless($this->canManageInsuranceCards(), 403);

        app(InsuranceCardPrintMarker::class)->toggle($this->getRecord(), $personKey);

        $this->refreshInsuranceCardRecords();
    }

    public function printedInsuranceCardCount(): int
    {
        $registration = $this->getRecord();
        $registration->loadMissing(['employee', 'beneficiaries']);

        $count = $registration->employee?->cardIsPrinted() ? 1 : 0;

        return $count + $registration->beneficiaries->filter(
            fn (Beneficiary $beneficiary): bool => $beneficiary->cardIsPrinted(),
        )->count();
    }

    private function refreshInsuranceCardRecords(): void
    {
        $this->record->refresh()->loadMissing(['employee', 'beneficiaries', 'reviewer', 'reviewLogs.user']);
    }
}
