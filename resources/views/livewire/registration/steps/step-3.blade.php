@php
    $medicalQuestions = [
        ['prop' => 'hasTumor', 'ar' => 'هل تعاني من أورام؟'],
        ['prop' => 'hasSurgeryHistory', 'ar' => 'هل خضعت لعمليات جراحية سابقة؟'],
        ['prop' => 'usesMedicalDevices', 'ar' => 'هل تستخدم أجهزة أو مستلزمات طبية؟'],
        ['prop' => 'hospitalizedRecently', 'ar' => 'هل أُدخلت المستشفى خلال الـ 12 شهراً الماضية؟'],
        ['prop' => 'traveledForTreatment', 'ar' => 'هل سبق السفر للعلاج بالخارج؟'],
    ];
@endphp

<section class="reg-card">
    <div class="reg-card-header">
        <h2 class="reg-card-title">السجل الطبي</h2>
        <p class="reg-card-subtitle">اختر «نعم» أو «لا» لكل سؤال — تُحفظ إجاباتك تلقائياً.</p>
    </div>

    <div class="space-y-3">
        <x-reg-medical-question
            question="هل تعاني من أمراض مزمنة؟"
            property="hasChronicConditions"
            :value="$hasChronicConditions"
        >
            @if ($hasChronicConditions)
                <div class="reg-medical-expand" wire:transition>
                    <p class="reg-medical-expand-title">حدد الأمراض المزمنة:</p>
                    <div class="reg-chronic-grid">
                        @foreach ($chronicConditionOptions as $key => $label)
                            <label class="reg-chronic-option" wire:key="chronic-{{ $key }}">
                                <input
                                    wire:model.live="chronicConditions"
                                    type="checkbox"
                                    value="{{ $key }}"
                                    class="reg-chronic-checkbox"
                                >
                                <span class="reg-chronic-label">{{ $label }}</span>
                                <span class="reg-chronic-check" aria-hidden="true">
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('chronicConditions') <p class="reg-field-error mt-3">{{ $message }}</p> @enderror
                </div>
            @endif
        </x-reg-medical-question>

        @foreach ($medicalQuestions as $q)
            <x-reg-medical-question
                :question="$q['ar']"
                :property="$q['prop']"
                :value="$this->{$q['prop']}"
            />
        @endforeach
    </div>
</section>

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'saveMedicalRecord',
    'primaryLabel' => 'حفظ ومتابعة',
])
