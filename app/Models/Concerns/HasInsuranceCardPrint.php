<?php

namespace App\Models\Concerns;

use DateTimeInterface;

trait HasInsuranceCardPrint
{
    public function cardIsPrinted(): bool
    {
        return $this->card_printed_at !== null;
    }

    public function cardPrintedLabel(): string
    {
        if (! $this->cardIsPrinted()) {
            return 'لم تُطبع';
        }

        return 'طُبعت · '.$this->card_printed_at->format('Y-m-d');
    }

    public function markCardPrinted(?DateTimeInterface $printedAt = null): void
    {
        $this->forceFill([
            'card_printed_at' => $printedAt ?? now(),
        ])->save();
    }

    public function clearCardPrinted(): void
    {
        $this->forceFill([
            'card_printed_at' => null,
        ])->save();
    }

    public function toggleCardPrinted(): void
    {
        if ($this->cardIsPrinted()) {
            $this->clearCardPrinted();

            return;
        }

        $this->markCardPrinted();
    }
}
