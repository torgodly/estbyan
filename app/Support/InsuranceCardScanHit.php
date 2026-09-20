<?php

namespace App\Support;

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
    ) {}

    public function cardLabel(): string
    {
        return InsuranceCardNumber::display($this->cardNumber);
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
