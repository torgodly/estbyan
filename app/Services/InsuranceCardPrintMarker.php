<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use App\Support\InsuranceCardNumber;

class InsuranceCardPrintMarker
{
    public function mark(MedicalRegistration $registration, ?string $personKey = null): void
    {
        $registration->loadMissing(['employee', 'beneficiaries']);

        if ($personKey === null || $personKey === '' || $personKey === 'employee') {
            $registration->employee?->markCardPrinted();
        }

        if ($personKey === null || $personKey === '') {
            $registration->beneficiaries->each(
                fn (Beneficiary $beneficiary) => $this->markBeneficiary($beneficiary),
            );

            return;
        }

        $beneficiary = $this->beneficiaryFor($registration, $personKey);

        if ($beneficiary) {
            $this->markBeneficiary($beneficiary);
        }
    }

    public function toggle(MedicalRegistration $registration, string $personKey): void
    {
        $registration->loadMissing(['employee', 'beneficiaries']);

        if ($personKey === 'employee') {
            $registration->employee?->toggleCardPrinted();

            return;
        }

        $beneficiary = $this->beneficiaryFor($registration, $personKey);

        if ($beneficiary === null) {
            return;
        }

        if ($beneficiary->cardIsPrinted()) {
            $this->clearBeneficiary($beneficiary);

            return;
        }

        $this->markBeneficiary($beneficiary);
    }

    private function markBeneficiary(Beneficiary $beneficiary): void
    {
        $printedAt = now();

        $beneficiary->markCardPrinted($printedAt);

        if (! InsuranceCardNumber::isValid($beneficiary->card_number)) {
            return;
        }

        Beneficiary::query()
            ->where('card_number', $beneficiary->card_number)
            ->whereKeyNot($beneficiary->id)
            ->update(['card_printed_at' => $printedAt]);
    }

    private function clearBeneficiary(Beneficiary $beneficiary): void
    {
        $beneficiary->clearCardPrinted();

        if (! InsuranceCardNumber::isValid($beneficiary->card_number)) {
            return;
        }

        Beneficiary::query()
            ->where('card_number', $beneficiary->card_number)
            ->whereKeyNot($beneficiary->id)
            ->update(['card_printed_at' => null]);
    }

    private function beneficiaryFor(MedicalRegistration $registration, string $personKey): ?Beneficiary
    {
        if (preg_match('/^beneficiary-(\d+)$/', $personKey, $matches) !== 1) {
            return null;
        }

        return $registration->beneficiaries->firstWhere('id', (int) $matches[1]);
    }
}
