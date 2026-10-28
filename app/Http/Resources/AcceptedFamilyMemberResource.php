<?php

namespace App\Http\Resources;

use App\Models\Beneficiary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Beneficiary */
class AcceptedFamilyMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Beneficiary $beneficiary */
        $beneficiary = $this->resource;
        $registration = $beneficiary->medicalRegistration;

        return [
            'id' => $beneficiary->id,
            'medical_registration_id' => $beneficiary->medical_registration_id,
            'full_name' => $beneficiary->full_name,
            'relationship' => $beneficiary->relationship?->value,
            'relationship_label' => $beneficiary->relationship?->label($registration?->gender),
            'is_libyan' => $beneficiary->is_libyan,
            'nationality' => $beneficiary->nationality,
            'nationality_label' => $beneficiary->nationalityLabel(),
            'national_id' => $beneficiary->national_id,
            'passport_number' => $beneficiary->passport_number,
            'date_of_birth' => $beneficiary->date_of_birth?->toDateString(),
            'blood_type' => $beneficiary->blood_type?->value,
            'blood_type_label' => $beneficiary->blood_type?->label(),
            'blood_type_symbol' => $beneficiary->blood_type?->cardLabel(),
            'card_number' => $beneficiary->card_number,
            'card_number_label' => $beneficiary->cardNumberLabel(),
            'card_printed_at' => $beneficiary->card_printed_at?->toIso8601String(),
            'card_is_printed' => $beneficiary->cardIsPrinted(),
            'has_chronic_condition' => $beneficiary->has_chronic_condition,
            'has_chronic_conditions' => $beneficiary->has_chronic_conditions,
            'chronic_conditions' => $beneficiary->chronic_conditions,
            'has_tumor' => $beneficiary->has_tumor,
            'has_surgery_history' => $beneficiary->has_surgery_history,
            'uses_medical_devices' => $beneficiary->uses_medical_devices,
            'hospitalized_recently' => $beneficiary->hospitalized_recently,
            'traveled_for_treatment' => $beneficiary->traveled_for_treatment,
            'photo' => filled($beneficiary->photo_path) && $registration !== null
                ? route('api.accepted-employees.family-member-photo', [$registration, $beneficiary])
                : null,
            'created_at' => $beneficiary->created_at?->toIso8601String(),
            'updated_at' => $beneficiary->updated_at?->toIso8601String(),
        ];
    }
}
