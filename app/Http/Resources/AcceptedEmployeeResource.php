<?php

namespace App\Http\Resources;

use App\Models\Beneficiary;
use App\Models\MedicalRegistration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MedicalRegistration */
class AcceptedEmployeeResource extends JsonResource
{
    /**
     * Open review flags, keyed by employee / beneficiary ID.
     *
     * @var array{employees: array<int, true>, beneficiaries: array<int, true>}
     */
    private array $openReviewFlags = ['employees' => [], 'beneficiaries' => []];

    /**
     * @param  array{employees: array<int, true>, beneficiaries: array<int, true>}  $openReviewFlags
     */
    public function withOpenReviewFlags(array $openReviewFlags): static
    {
        $this->openReviewFlags = $openReviewFlags;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MedicalRegistration $registration */
        $registration = $this->resource;
        $employee = $registration->employee;

        $registration->beneficiaries->each(
            fn (Beneficiary $beneficiary) => $beneficiary->setRelation('medicalRegistration', $registration),
        );

        return [
            'id' => $registration->employee_id,
            'uuid' => $registration->uuid,
            'registration_id' => $registration->id,
            'reference_number' => $registration->reference_number,
            'status' => $registration->status->value,
            'status_label' => $registration->status->label(),
            'current_step' => $registration->current_step,
            'review_note' => $registration->review_note,
            'reviewed_by' => $registration->reviewed_by,
            'reviewer_name' => $registration->reviewer?->name,
            'employee_number' => $registration->employee_number,
            'national_id' => $registration->national_id,
            'full_name' => $registration->full_name,
            'date_of_birth' => $registration->date_of_birth?->toDateString(),
            'gender' => $registration->gender?->value,
            'gender_label' => $registration->gender?->label(),
            'blood_type' => $registration->blood_type?->value,
            'blood_type_label' => $registration->blood_type?->label(),
            'blood_type_symbol' => $registration->blood_type?->cardLabel(),
            'marital_status' => $registration->marital_status?->value,
            'marital_status_label' => $registration->marital_status?->label(),
            'workplace' => $registration->workplace,
            'workplace_label' => $registration->workplaceLabel(),
            'office' => $employee?->office,
            'job_title' => $registration->job_title,
            'job_title_label' => $registration->jobTitleLabel(),
            'phone' => $registration->phone,
            'whatsapp' => $registration->whatsapp,
            'email' => $registration->email,
            'city' => $registration->city,
            'city_label' => $registration->cityLabel(),
            'address' => $registration->address,
            'card_number' => $employee?->card_number,
            'card_number_label' => $employee?->cardNumberLabel(),
            'card_printed_at' => $employee?->card_printed_at?->toIso8601String(),
            'card_is_printed' => (bool) $employee?->cardIsPrinted(),
            'is_active' => $employee?->is_active,
            'cards_delivered_at' => $employee?->cards_delivered_at?->toIso8601String(),
            'cards_delivered_to' => $employee?->cards_delivered_to?->value,
            'cards_delivered_to_label' => $employee?->cards_delivered_to?->getLabel(),
            'cards_delivered_by' => $employee?->cards_delivered_by,
            'cards_delivered_by_name' => $employee?->cardsDeliveredBy?->name,
            'has_chronic_conditions' => $registration->has_chronic_conditions,
            'chronic_conditions' => $registration->chronic_conditions,
            'has_tumor' => $registration->has_tumor,
            'has_surgery_history' => $registration->has_surgery_history,
            'uses_medical_devices' => $registration->uses_medical_devices,
            'hospitalized_recently' => $registration->hospitalized_recently,
            'traveled_for_treatment' => $registration->traveled_for_treatment,
            'beneficiaries_count' => $registration->beneficiaries->count(),
            'needs_review' => isset($this->openReviewFlags['employees'][$registration->employee_id]),
            'consent_at' => $registration->consent_at?->toIso8601String(),
            'submitted_at' => $registration->submitted_at?->toIso8601String(),
            'reviewed_at' => $registration->reviewed_at?->toIso8601String(),
            'created_at' => $registration->created_at?->toIso8601String(),
            'updated_at' => $registration->updated_at?->toIso8601String(),
            'photo' => filled($registration->employee_photo_path)
                ? route('api.accepted-employees.photo', $registration)
                : null,
            'family_members' => $registration->beneficiaries
                ->map(fn (Beneficiary $beneficiary): array => [
                    ...(new AcceptedFamilyMemberResource($beneficiary))->resolve($request),
                    'needs_review' => isset($this->openReviewFlags['beneficiaries'][$beneficiary->id]),
                ])
                ->values()
                ->all(),
        ];
    }
}
