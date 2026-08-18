@php
    $genderLabel = \App\Enums\Gender::tryFrom($gender)?->label() ?? '—';
    $maritalLabel = \App\Enums\MaritalStatus::tryFrom($maritalStatus)?->label() ?? '—';
    $registration = $this->registration();
    $employeePhotoUrl = $registration
        ? \App\Support\RegistrationDocuments::url($registration, \App\Support\RegistrationDocuments::EMPLOYEE_PHOTO)
        : null;
@endphp

<section class="space-y-5">
    <div class="reg-card">
        <div class="reg-card-header !mb-0 !border-0 !pb-0">
            <h2 class="reg-card-title">تقرير المراجعة النهائية</h2>
            <p class="reg-card-subtitle">راجع جميع بياناتك وبيانات عائلتك بعناية قبل تأكيد الإرسال.</p>
        </div>
    </div>

    <article class="reg-report-card">
        <div class="reg-report-head">
            <div class="min-w-0">
                <p class="reg-report-kicker">بيانات الموظف</p>
                <h3 class="reg-report-name">{{ $verifiedFullName }}</h3>
                <p class="mt-1 text-sm text-slate-500" dir="ltr">{{ $nationalId }} · {{ $employeeNumber }}</p>
            </div>
            @if ($employeePhotoUrl)
                <img src="{{ $employeePhotoUrl }}" alt="صورة الموظف" class="reg-report-avatar">
            @else
                <div class="reg-report-avatar-fallback">{{ mb_substr($verifiedFullName, 0, 1) }}</div>
            @endif
        </div>

        <div class="reg-report-grid">
            <div class="reg-report-item">
                <dt>مكان العمل</dt>
                <dd>{{ $workplaces[$workplace] ?? '—' }}</dd>
            </div>
            <div class="reg-report-item">
                <dt>المسمى الوظيفي</dt>
                <dd>{{ $jobTitles[$jobTitle] ?? '—' }}</dd>
            </div>
            <div class="reg-report-item">
                <dt>تاريخ الميلاد</dt>
                <dd>{{ $dateOfBirth ?: '—' }}</dd>
            </div>
            <div class="reg-report-item">
                <dt>الجنس</dt>
                <dd>{{ $genderLabel }}</dd>
            </div>
            <div class="reg-report-item">
                <dt>الحالة الاجتماعية</dt>
                <dd>{{ $maritalLabel }}</dd>
            </div>
            <div class="reg-report-item">
                <dt>عدد المستفيدين</dt>
                <dd>{{ $beneficiariesCount !== '' ? $beneficiariesCount : '—' }}</dd>
            </div>
            <div class="reg-report-item">
                <dt>الهاتف</dt>
                <dd dir="ltr">{{ $phone ?: '—' }}</dd>
            </div>
            <div class="reg-report-item">
                <dt>واتساب</dt>
                <dd dir="ltr">{{ $whatsapp ?: '—' }}</dd>
            </div>
            <div class="reg-report-item">
                <dt>البريد</dt>
                <dd dir="ltr">{{ $email ?: '—' }}</dd>
            </div>
            <div class="reg-report-item">
                <dt>المدينة</dt>
                <dd>{{ $cities[$city] ?? '—' }}</dd>
            </div>
            <div class="reg-report-item reg-report-item-full">
                <dt>العنوان</dt>
                <dd>{{ $address ?: '—' }}</dd>
            </div>
        </div>
    </article>

    <article class="reg-report-card">
        <p class="reg-report-kicker">السجل الطبي للموظف</p>
        <div class="reg-report-flags">
            <span @class(['reg-report-flag', 'reg-report-flag-yes' => $hasChronicConditions])>أمراض مزمنة: {{ $hasChronicConditions ? 'نعم' : 'لا' }}</span>
            <span @class(['reg-report-flag', 'reg-report-flag-yes' => $hasTumor])>أورام: {{ $hasTumor ? 'نعم' : 'لا' }}</span>
            <span @class(['reg-report-flag', 'reg-report-flag-yes' => $hasSurgeryHistory])>عمليات: {{ $hasSurgeryHistory ? 'نعم' : 'لا' }}</span>
            <span @class(['reg-report-flag', 'reg-report-flag-yes' => $usesMedicalDevices])>أجهزة طبية: {{ $usesMedicalDevices ? 'نعم' : 'لا' }}</span>
            <span @class(['reg-report-flag', 'reg-report-flag-yes' => $hospitalizedRecently])>مستشفى: {{ $hospitalizedRecently ? 'نعم' : 'لا' }}</span>
            <span @class(['reg-report-flag', 'reg-report-flag-yes' => $traveledForTreatment])>علاج بالخارج: {{ $traveledForTreatment ? 'نعم' : 'لا' }}</span>
        </div>
        @if ($hasChronicConditions && count($chronicConditions))
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($chronicConditions as $condition)
                    <span class="reg-tag reg-tag-chronic">{{ $chronicConditionOptions[$condition] ?? $condition }}</span>
                @endforeach
            </div>
        @endif
    </article>

    <section class="space-y-4">
        <div class="reg-report-card !pb-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="reg-report-kicker">المستفيدون</p>
                    <h3 class="text-lg font-extrabold text-navy-900">أفراد العائلة المسجلون</h3>
                    <p class="mt-1 text-sm text-slate-500">راجع بيانات كل مستفيد وسجله الطبي قبل الإرسال.</p>
                </div>
                <div class="reg-report-count">
                    <span class="reg-report-count-value">{{ count($beneficiaries) }}</span>
                    <span class="reg-report-count-label">
                        @if ($beneficiariesCount !== '' && (int) $beneficiariesCount !== count($beneficiaries))
                            من {{ $beneficiariesCount }}
                        @else
                            مستفيد
                        @endif
                    </span>
                </div>
            </div>
        </div>

        @forelse ($beneficiaries as $index => $beneficiary)
            @php
                $rel = \App\Enums\BeneficiaryRelationship::from($beneficiary['relationship']);
                $blood = \App\Enums\BloodType::tryFrom($beneficiary['blood_type'] ?? '');
                $photo = $this->beneficiaryPhotoUrl($beneficiary);
                $hasChronic = (bool) ($beneficiary['has_chronic_conditions'] ?? $beneficiary['has_chronic_condition'] ?? false);
                $medicalAnswers = [
                    ['label' => 'أمراض مزمنة', 'yes' => $hasChronic],
                    ['label' => 'أورام', 'yes' => (bool) ($beneficiary['has_tumor'] ?? false)],
                    ['label' => 'عمليات جراحية', 'yes' => (bool) ($beneficiary['has_surgery_history'] ?? false)],
                    ['label' => 'أجهزة طبية', 'yes' => (bool) ($beneficiary['uses_medical_devices'] ?? false)],
                    ['label' => 'دخول مستشفى (12 شهر)', 'yes' => (bool) ($beneficiary['hospitalized_recently'] ?? false)],
                    ['label' => 'علاج بالخارج', 'yes' => (bool) ($beneficiary['traveled_for_treatment'] ?? false)],
                ];
                $medicalYesCount = collect($medicalAnswers)->where('yes', true)->count();
                $chronicList = $hasChronic ? ($beneficiary['chronic_conditions'] ?? []) : [];
            @endphp

            <article class="reg-report-member" wire:key="review-beneficiary-{{ $index }}">
                <header class="reg-report-member-head">
                    <div class="flex min-w-0 items-start gap-3">
                        <span class="reg-report-member-index" aria-hidden="true">{{ $index + 1 }}</span>
                        @if ($photo)
                            <img src="{{ $photo }}" alt="صورة {{ $beneficiary['full_name'] }}" class="reg-report-member-photo">
                        @else
                            <div class="reg-report-member-photo-fallback" aria-hidden="true">{{ $rel->icon() }}</div>
                        @endif
                        <div class="min-w-0 pt-0.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="truncate text-lg font-extrabold text-navy-900">{{ $beneficiary['full_name'] }}</h4>
                                <span class="reg-tag reg-tag-relation">{{ $rel->label() }}</span>
                            </div>
                            <p class="mt-1 text-sm text-slate-500" dir="ltr">{{ $beneficiary['national_id'] ?? '—' }}</p>
                        </div>
                    </div>
                    <span @class([
                        'reg-report-health-badge',
                        'reg-report-health-badge-alert' => $medicalYesCount > 0,
                    ])>
                        {{ $medicalYesCount > 0 ? "{$medicalYesCount} ملاحظة طبية" : 'لا ملاحظات طبية' }}
                    </span>
                </header>

                <div class="reg-report-member-body">
                    <div>
                        <p class="reg-report-section-label">بيانات الهوية</p>
                        <dl class="reg-report-grid">
                            <div class="reg-report-item">
                                <dt>صلة القرابة</dt>
                                <dd>{{ $rel->label() }}</dd>
                            </div>
                            <div class="reg-report-item">
                                <dt>تاريخ الميلاد</dt>
                                <dd>{{ $beneficiary['date_of_birth'] ?? '—' }}</dd>
                            </div>
                            <div class="reg-report-item">
                                <dt>الرقم الوطني</dt>
                                <dd dir="ltr">{{ $beneficiary['national_id'] ?? '—' }}</dd>
                            </div>
                            <div class="reg-report-item">
                                <dt>فصيلة الدم</dt>
                                <dd>{{ $blood?->label() ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <p class="reg-report-section-label">السجل الطبي</p>
                        <ul class="reg-report-checklist" role="list">
                            @foreach ($medicalAnswers as $answer)
                                <li @class(['reg-report-check', 'reg-report-check-yes' => $answer['yes']])>
                                    <span class="reg-report-check-label">{{ $answer['label'] }}</span>
                                    <span class="reg-report-check-value">{{ $answer['yes'] ? 'نعم' : 'لا' }}</span>
                                </li>
                            @endforeach
                        </ul>

                        @if (count($chronicList) > 0)
                            <div class="mt-3">
                                <p class="mb-2 text-xs font-bold text-amber-700">الأمراض المزمنة المحددة</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($chronicList as $condition)
                                        <span class="reg-tag reg-tag-chronic">{{ $chronicConditionOptions[$condition] ?? $condition }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </article>
        @empty
            <div class="reg-report-card">
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-10 text-center">
                    <p class="text-base font-extrabold text-navy-900">لا يوجد مستفيدون مضافون</p>
                    <p class="mt-1 text-sm text-slate-500">يمكنك الرجوع لخطوة العائلة لإضافة أفراد إن لزم الأمر.</p>
                </div>
            </div>
        @endforelse
    </section>

    <article class="reg-report-card">
        <p class="reg-report-kicker">المستندات</p>
        <ul class="space-y-2 text-sm">
            <li class="flex items-center gap-2 font-bold {{ ($hasFamilyDocument || $familyStatusDocument) ? 'text-teal-700' : 'text-red-600' }}">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                صورة من شهادة الوضع العائلي {{ ($hasFamilyDocument || $familyStatusDocument) ? '— مرفقة' : '— ناقصة' }}
            </li>
            <li class="flex items-center gap-2 font-bold {{ ($hasEmployeePhoto || $employeePhoto) ? 'text-teal-700' : 'text-red-600' }}">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                الصورة الشخصية {{ ($hasEmployeePhoto || $employeePhoto) ? '— مرفقة' : '— ناقصة' }}
            </li>
        </ul>
    </article>

    @if ($referenceNumber)
        <div class="rounded-2xl border border-teal-200 bg-teal-50/50 px-4 py-3 text-center text-sm">
            <span class="text-slate-500">رقم المرجع الحالي:</span>
            <span class="ms-2 font-mono font-extrabold text-navy-900" dir="ltr">{{ $referenceNumber }}</span>
        </div>
    @endif

    @error('submit') <p class="reg-field-error">{{ $message }}</p> @enderror
</section>

<div class="reg-actions">
    <div class="reg-actions-dock">
        <div class="reg-actions-inner">
            <button
                type="button"
                wire:click="submitRegistration"
                wire:loading.attr="disabled"
                wire:target="submitRegistration,goBack"
                class="reg-btn-primary lg:min-w-[12rem]"
            >
                <span wire:loading.remove wire:target="submitRegistration">تأكيد وإرسال التسجيل</span>
                <span wire:loading wire:target="submitRegistration" class="inline-flex items-center gap-2">
                    <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    جاري الإرسال...
                </span>
            </button>
            <button
                type="button"
                wire:click="goBack"
                wire:loading.attr="disabled"
                wire:target="submitRegistration,goBack"
                class="reg-btn-secondary lg:min-w-[8rem]"
            >
                <span wire:loading.remove wire:target="goBack">رجوع</span>
                <span wire:loading wire:target="goBack" class="inline-flex items-center gap-2">
                    <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    جاري الرجوع...
                </span>
            </button>
        </div>
    </div>
</div>
