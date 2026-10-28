<?php

namespace App\Support;

use App\Enums\BeneficiaryCancellationReason;
use Filament\Notifications\Notification;

final readonly class InsuranceCardScanHit
{
    /**
     * @param  'employee'|'beneficiary'  $kind
     */
    public function __construct(
        public string $cardNumber,
        public string $kind,
        public int $employeeId,
        public ?int $registrationId,
        public ?int $beneficiaryId,
        public string $name,
        public string $roleLabel,
        public string $personKey,
        public bool $isPrinted,
        public ?BeneficiaryCancellationReason $cancellationReason = null,
    ) {}

    public function cardLabel(): string
    {
        return InsuranceCardNumber::display($this->cardNumber);
    }

    public function isCancelled(): bool
    {
        return $this->cancellationReason !== null;
    }

    /**
     * Warn the operator that a cancelled card was scanned and must be kept, not handed over or reprinted.
     */
    public function sendCancelledNotification(): void
    {
        Notification::make()
            ->title('بطاقة ملغاة: '.$this->name)
            ->body($this->cancellationReason?->scanWarning())
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * @return array{
     *     card_number: string,
     *     kind: string,
     *     employee_id: int,
     *     registration_id: int|null,
     *     beneficiary_id: int|null,
     *     name: string,
     *     role_label: string,
     *     person_key: string,
     *     is_printed: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'card_number' => $this->cardNumber,
            'kind' => $this->kind,
            'employee_id' => $this->employeeId,
            'registration_id' => $this->registrationId,
            'beneficiary_id' => $this->beneficiaryId,
            'name' => $this->name,
            'role_label' => $this->roleLabel,
            'person_key' => $this->personKey,
            'is_printed' => $this->isPrinted,
        ];
    }
}
