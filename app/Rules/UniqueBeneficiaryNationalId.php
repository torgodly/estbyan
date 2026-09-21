<?php

namespace App\Rules;

use App\Models\Beneficiary;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UniqueBeneficiaryNationalId implements ValidationRule
{
    /**
     * @param  list<string>  $siblingNationalIds
     */
    public function __construct(
        public readonly ?int $ignoreRegistrationId = null,
        public readonly ?string $employeeNationalId = null,
        public readonly array $siblingNationalIds = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return;
        }

        $nationalId = trim((string) $value);

        if ($nationalId === '') {
            return;
        }

        if (filled($this->employeeNationalId) && $nationalId === trim($this->employeeNationalId)) {
            $fail('لا يمكن استخدام الرقم الوطني للموظف نفسه كمستفيد.');

            return;
        }

        if (in_array($nationalId, $this->normalizedSiblingNationalIds(), true)) {
            $fail('هذا الرقم الوطني مضاف بالفعل لمستفيد آخر في هذه الاستمارة.');

            return;
        }

        $existing = Beneficiary::query()
            ->with('medicalRegistration')
            ->whereNotNull('national_id')
            ->where('national_id', '!=', '')
            ->where('national_id', $nationalId)
            ->when(
                $this->ignoreRegistrationId !== null,
                fn ($query) => $query->where('medical_registration_id', '!=', $this->ignoreRegistrationId),
            )
            ->first();

        if ($existing === null) {
            return;
        }

        $owner = $existing->medicalRegistration?->full_name;

        $fail($owner
            ? "هذا الرقم الوطني مسجّل بالفعل ضمن عائلة {$owner}."
            : 'هذا الرقم الوطني مسجّل بالفعل كمستفيد لموظف آخر.');
    }

    /**
     * @return list<string>
     */
    protected function normalizedSiblingNationalIds(): array
    {
        return array_values(array_filter(
            array_map(
                fn (string $nationalId): string => trim($nationalId),
                $this->siblingNationalIds,
            ),
            fn (string $nationalId): bool => $nationalId !== '',
        ));
    }
}
