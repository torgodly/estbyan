@php
    use App\Support\RegistrationDocuments;

    $registration = $this->getRecord();
    $photoUrl = RegistrationDocuments::url($registration, RegistrationDocuments::EMPLOYEE_PHOTO);
    $familyDocUrl = RegistrationDocuments::url($registration, RegistrationDocuments::FAMILY_STATUS);
    $chronicLabels = collect($registration->chronic_conditions ?? [])
        ->map(fn (string $key) => config('registration.chronic_conditions.'.$key) ?? $key)
        ->filter()
        ->values();
    $medicalFlags = [
        [
            'label' => 'هل يعاني من أمراض مزمنة؟',
            'value' => (bool) $registration->has_chronic_conditions,
            'key' => 'chronic',
            'details' => $chronicLabels,
            'detail_title' => 'الأمراض المزمنة المحددة',
            'empty_yes' => 'تم التحديد بنعم دون حفظ تفاصيل إضافية.',
            'empty_no' => 'لا يعاني من أمراض مزمنة.',
        ],
        [
            'label' => 'هل يوجد تاريخ أورام؟',
            'value' => (bool) $registration->has_tumor,
            'key' => 'tumor',
            'details' => collect(),
            'detail_title' => 'التفاصيل',
            'empty_yes' => 'تم التحديد بنعم. لا توجد خيارات تفصيلية إضافية في النموذج.',
            'empty_no' => 'لا يوجد تاريخ أورام.',
        ],
        [
            'label' => 'هل أجرى عمليات جراحية؟',
            'value' => (bool) $registration->has_surgery_history,
            'key' => 'surgery',
            'details' => collect(),
            'detail_title' => 'التفاصيل',
            'empty_yes' => 'تم التحديد بنعم. لا توجد خيارات تفصيلية إضافية في النموذج.',
            'empty_no' => 'لا توجد عمليات جراحية.',
        ],
        [
            'label' => 'هل يستخدم أجهزة طبية؟',
            'value' => (bool) $registration->uses_medical_devices,
            'key' => 'devices',
            'details' => collect(),
            'detail_title' => 'التفاصيل',
            'empty_yes' => 'تم التحديد بنعم. لا توجد خيارات تفصيلية إضافية في النموذج.',
            'empty_no' => 'لا يستخدم أجهزة طبية.',
        ],
        [
            'label' => 'هل أقام في مستشفى مؤخراً؟',
            'value' => (bool) $registration->hospitalized_recently,
            'key' => 'hospital',
            'details' => collect(),
            'detail_title' => 'التفاصيل',
            'empty_yes' => 'تم التحديد بنعم. لا توجد خيارات تفصيلية إضافية في النموذج.',
            'empty_no' => 'لا توجد إقامة مستشفى حديثة.',
        ],
        [
            'label' => 'هل سافر للعلاج بالخارج؟',
            'value' => (bool) $registration->traveled_for_treatment,
            'key' => 'abroad',
            'details' => collect(),
            'detail_title' => 'التفاصيل',
            'empty_yes' => 'تم التحديد بنعم. لا توجد خيارات تفصيلية إضافية في النموذج.',
            'empty_no' => 'لم يسافر للعلاج بالخارج.',
        ],
    ];
    $positiveFlags = collect($medicalFlags)->where('value', true)->values();
    $defaultOpen = $positiveFlags->first()['key'] ?? 'chronic';
    $familyDocType = filled($registration->family_status_document_path)
        && preg_match('/\.(jpe?g|png|webp|gif)$/i', $registration->family_status_document_path)
        ? 'image'
        : 'pdf';
@endphp

<x-filament-panels::page>
    <div
        dir="rtl"
        class="hr-review"
        x-data="{
            previewOpen: false,
            previewUrl: null,
            previewType: null,
            previewTitle: '',
            openMedical: @js($defaultOpen),
            openBeneficiary: {},
            openPreview(url, type, title) {
                this.previewUrl = url
                this.previewType = type
                this.previewTitle = title
                this.previewOpen = true
            },
            closePreview() {
                this.previewOpen = false
                this.previewUrl = null
            },
            toggleMedical(key) {
                this.openMedical = this.openMedical === key ? null : key
            },
            toggleBeneficiary(id, key) {
                const current = this.openBeneficiary[id] ?? null
                this.openBeneficiary[id] = current === key ? null : key
            },
        }"
        @keydown.escape.window="closePreview()"
    >
        <div class="hr-review__layout">
            <div class="hr-review__main">
                {{-- Hero identity --}}
                <section class="hr-panel">
                    <div class="hr-hero">
                        <div>
                            @if ($photoUrl)
                                <button
                                    type="button"
                                    class="hr-hero__photo-btn"
                                    @click="openPreview(@js($photoUrl), 'image', 'صورة الموظف')"
                                >
                                    <img src="{{ $photoUrl }}" alt="صورة {{ $registration->full_name }}" class="hr-hero__photo">
                                </button>
                            @else
                                <div class="hr-hero__photo-empty">
                                    <x-filament::icon icon="heroicon-o-user" class="h-8 w-8" />
                                    <span>لا توجد صورة</span>
                                </div>
                            @endif
                        </div>

                        <div>
                            <div class="hr-chips">
                                <span class="hr-chip hr-chip--{{ $registration->status->value }}">
                                    {{ $registration->status->label() }}
                                </span>
                                @if (filled($registration->reference_number))
                                    <span class="hr-chip">{{ $registration->reference_number }}</span>
                                @endif
                                <span class="hr-chip">{{ $registration->beneficiaries->count() }} مستفيد</span>
                            </div>

                            <h2 class="hr-hero__name">{{ $registration->full_name ?: 'بدون اسم' }}</h2>
                            <p class="hr-hero__sub">
                                {{ $registration->workplaceLabel() ?? 'مكان العمل غير محدد' }}
                                @if ($registration->jobTitleLabel())
                                    · {{ $registration->jobTitleLabel() }}
                                @endif
                            </p>

                            <div class="hr-kpis">
                                <div class="hr-kpi">
                                    <span class="hr-kpi__label">الرقم الوظيفي</span>
                                    <div class="hr-kpi__value">{{ $registration->employee_number ?: '—' }}</div>
                                </div>
                                <div class="hr-kpi">
                                    <span class="hr-kpi__label">الرقم الوطني</span>
                                    <div class="hr-kpi__value">{{ $registration->national_id ?: '—' }}</div>
                                </div>
                                <div class="hr-kpi">
                                    <span class="hr-kpi__label">تاريخ الإرسال</span>
                                    <div class="hr-kpi__value">{{ $registration->submitted_at?->format('Y-m-d H:i') ?: '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                @if ($positiveFlags->isNotEmpty())
                    <div class="hr-alert {{ $registration->has_tumor ? 'hr-alert--danger' : '' }}">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                        <div>
                            <p class="hr-alert__title">تنبيه طبي — يوجد {{ $positiveFlags->count() }} إجابة بـ «نعم»</p>
                            <p class="hr-alert__text">
                                {{ $positiveFlags->pluck('label')->map(fn ($label) => str_replace(['هل ', '؟'], '', $label))->implode(' · ') }}
                            </p>
                        </div>
                    </div>
                @endif

                {{-- Personal + contact --}}
                <section class="hr-panel">
                    <div class="hr-panel__head">
                        <h3 class="hr-panel__title">البيانات الشخصية والتواصل</h3>
                    </div>
                    <div class="hr-panel__body">
                        <div class="hr-rows">
                            @foreach ([
                                'تاريخ الميلاد' => $registration->date_of_birth?->format('Y-m-d') ?: '—',
                                'الجنس' => $registration->gender?->label() ?? '—',
                                'الحالة الاجتماعية' => $registration->marital_status?->label() ?? '—',
                                'الهاتف' => $registration->phone ?: '—',
                                'واتساب' => $registration->whatsapp ?: '—',
                                'البريد الإلكتروني' => $registration->email ?: '—',
                                'المدينة' => $registration->cityLabel() ?? '—',
                                'العنوان' => $registration->address ?: '—',
                            ] as $label => $value)
                                <div class="hr-row">
                                    <div class="hr-row__label">{{ $label }}</div>
                                    <div class="hr-row__value">{{ $value }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Medical record accordion --}}
                <section class="hr-panel" id="medical-record">
                    <div class="hr-panel__head">
                        <h3 class="hr-panel__title">السجل الطبي للموظف</h3>
                        <span class="hr-panel__meta">
                            {{ $positiveFlags->isEmpty() ? 'لا توجد مؤشرات إيجابية' : $positiveFlags->count().' مؤشرات إيجابية' }}
                        </span>
                    </div>
                    <div class="hr-panel__body">
                        <div class="hr-accordion">
                            @foreach ($medicalFlags as $flag)
                                <div class="hr-accordion__item" :class="{ 'is-open': openMedical === @js($flag['key']) }">
                                    <button
                                        type="button"
                                        class="hr-accordion__trigger"
                                        @click="toggleMedical(@js($flag['key']))"
                                    >
                                        <span class="hr-accordion__question">
                                            <strong>{{ $flag['label'] }}</strong>
                                            <span class="hr-accordion__hint">
                                                {{ $flag['value'] ? 'اضغط لعرض التفاصيل المحددة' : 'اضغط لعرض تفاصيل الإجابة' }}
                                            </span>
                                        </span>
                                        <span class="hr-accordion__aside">
                                            <span @class([
                                                'hr-answer',
                                                'hr-answer--yes' => $flag['value'],
                                                'hr-answer--no' => ! $flag['value'],
                                            ])>
                                                {{ $flag['value'] ? 'نعم' : 'لا' }}
                                            </span>
                                            <x-filament::icon icon="heroicon-m-chevron-down" class="hr-accordion__chevron" />
                                        </span>
                                    </button>
                                    <div class="hr-accordion__panel" x-show="openMedical === @js($flag['key'])" x-cloak>
                                        <p class="hr-accordion__panel-title">{{ $flag['detail_title'] }}</p>
                                        @if ($flag['value'] && $flag['details']->isNotEmpty())
                                            <div class="hr-tags" style="margin-top: 0;">
                                                @foreach ($flag['details'] as $label)
                                                    <span class="hr-tag">{{ $label }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="hr-accordion__empty">
                                                {{ $flag['value'] ? $flag['empty_yes'] : $flag['empty_no'] }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                {{-- Documents with inline preview --}}
                <section class="hr-panel">
                    <div class="hr-panel__head">
                        <h3 class="hr-panel__title">المستندات</h3>
                        <span class="hr-panel__meta">معاينة مباشرة داخل الصفحة</span>
                    </div>
                    <div class="hr-panel__body">
                        <div class="hr-docs">
                            <div class="hr-doc">
                                <div class="hr-doc__bar">
                                    <div class="hr-doc__title">صورة من شهادة الوضع العائلي</div>
                                    @if ($familyDocUrl)
                                        <button
                                            type="button"
                                            class="hr-doc__action"
                                            @click="openPreview(@js($familyDocUrl), @js($familyDocType), 'صورة من شهادة الوضع العائلي')"
                                        >
                                            تكبير
                                        </button>
                                    @endif
                                </div>
                                <div class="hr-doc__frame">
                                    @if ($familyDocUrl)
                                        @if ($familyDocType === 'image')
                                            <img src="{{ $familyDocUrl }}" alt="صورة من شهادة الوضع العائلي">
                                        @else
                                            <iframe src="{{ $familyDocUrl }}#toolbar=0" title="صورة من شهادة الوضع العائلي"></iframe>
                                        @endif
                                    @else
                                        <div class="hr-doc__missing">
                                            <x-filament::icon icon="heroicon-o-document" class="h-7 w-7" />
                                            <span>لم يُرفق هذا المستند</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="hr-doc">
                                <div class="hr-doc__bar">
                                    <div class="hr-doc__title">صورة الموظف</div>
                                    @if ($photoUrl)
                                        <button
                                            type="button"
                                            class="hr-doc__action"
                                            @click="openPreview(@js($photoUrl), 'image', 'صورة الموظف')"
                                        >
                                            تكبير
                                        </button>
                                    @endif
                                </div>
                                <div class="hr-doc__frame">
                                    @if ($photoUrl)
                                        <img src="{{ $photoUrl }}" alt="صورة الموظف">
                                    @else
                                        <div class="hr-doc__missing">
                                            <x-filament::icon icon="heroicon-o-photo" class="h-7 w-7" />
                                            <span>لم تُرفع صورة الموظف</span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- Beneficiaries --}}
                <section class="hr-panel">
                    <div class="hr-panel__head">
                        <h3 class="hr-panel__title">المستفيدون</h3>
                        <span class="hr-panel__meta">{{ $registration->beneficiaries->count() }} مستفيد</span>
                    </div>
                    <div class="hr-panel__body">
                        @forelse ($registration->beneficiaries as $index => $beneficiary)
                            @php
                                $beneficiaryPhoto = RegistrationDocuments::beneficiaryUrl($registration, $beneficiary);
                                $beneficiaryChronic = collect($beneficiary->chronic_conditions ?? [])
                                    ->map(fn (string $key) => config('registration.chronic_conditions.'.$key) ?? $key)
                                    ->filter()
                                    ->values();
                                $beneficiaryFlags = [
                                    ['label' => 'أمراض مزمنة', 'value' => (bool) $beneficiary->has_chronic_conditions],
                                    ['label' => 'أورام', 'value' => (bool) $beneficiary->has_tumor],
                                    ['label' => 'عمليات', 'value' => (bool) $beneficiary->has_surgery_history],
                                    ['label' => 'أجهزة طبية', 'value' => (bool) $beneficiary->uses_medical_devices],
                                    ['label' => 'إقامة مستشفى', 'value' => (bool) $beneficiary->hospitalized_recently],
                                    ['label' => 'علاج بالخارج', 'value' => (bool) $beneficiary->traveled_for_treatment],
                                ];
                            @endphp
                            <article class="hr-ben__item" style="{{ $index > 0 ? 'margin-top: 0.75rem;' : '' }}">
                                <div>
                                    @if ($beneficiaryPhoto)
                                        <button
                                            type="button"
                                            class="hr-hero__photo-btn"
                                            @click="openPreview(@js($beneficiaryPhoto), 'image', @js($beneficiary->full_name))"
                                        >
                                            <img src="{{ $beneficiaryPhoto }}" alt="" class="hr-ben__photo">
                                        </button>
                                    @else
                                        <div class="hr-ben__photo-empty">بدون صورة</div>
                                    @endif
                                </div>
                                <div>
                                    <h4 class="hr-ben__name">{{ $index + 1 }}. {{ $beneficiary->full_name }}</h4>
                                    <p class="hr-ben__rel">{{ $beneficiary->relationship?->label() ?? '—' }}</p>

                                    <div class="hr-kpis" style="margin-top: 0;">
                                        <div class="hr-kpi">
                                            <span class="hr-kpi__label">الرقم الوطني</span>
                                            <div class="hr-kpi__value">{{ $beneficiary->national_id ?: '—' }}</div>
                                        </div>
                                        <div class="hr-kpi">
                                            <span class="hr-kpi__label">تاريخ الميلاد</span>
                                            <div class="hr-kpi__value">{{ $beneficiary->date_of_birth?->format('Y-m-d') ?: '—' }}</div>
                                        </div>
                                        <div class="hr-kpi">
                                            <span class="hr-kpi__label">فصيلة الدم</span>
                                            <div class="hr-kpi__value">{{ $beneficiary->blood_type?->label() ?? '—' }}</div>
                                        </div>
                                    </div>

                                    <table class="hr-med-table" style="margin-top: 0.85rem;">
                                        <tbody>
                                            @foreach ($beneficiaryFlags as $flag)
                                                <tr>
                                                    <th scope="row">{{ $flag['label'] }}</th>
                                                    <td>
                                                        <span @class([
                                                            'hr-answer',
                                                            'hr-answer--yes' => $flag['value'],
                                                            'hr-answer--no' => ! $flag['value'],
                                                        ])>
                                                            {{ $flag['value'] ? 'نعم' : 'لا' }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                            <tr>
                                                <th scope="row">الأمراض المزمنة</th>
                                                <td>
                                                    @if ($beneficiaryChronic->isNotEmpty())
                                                        <div class="hr-tags" style="margin-top: 0;">
                                                            @foreach ($beneficiaryChronic as $label)
                                                                <span class="hr-tag">{{ $label }}</span>
                                                            @endforeach
                                                        </div>
                                                    @else
                                                        <span class="hr-answer hr-answer--no">لا يوجد</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        @empty
                            <div class="hr-empty">
                                <x-filament::icon icon="heroicon-o-user-group" class="h-7 w-7" />
                                <p class="hr-empty__title">لا يوجد مستفيدون على هذا الطلب</p>
                                <p class="hr-empty__text">سيظهرون هنا عند إضافتهم من النموذج العام.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>

            {{-- Sticky review rail --}}
            <aside class="hr-review__aside">
                <section class="hr-panel">
                    <div class="hr-panel__head">
                        <h3 class="hr-panel__title">ملخص المراجعة</h3>
                    </div>
                    <div class="hr-panel__body" style="display: grid; gap: 0.65rem;">
                        <div class="hr-side-stat">
                            <span class="hr-side-stat__label">الحالة الحالية</span>
                            <div class="hr-side-stat__value">{{ $registration->status->label() }}</div>
                        </div>
                        <div class="hr-side-stat">
                            <span class="hr-side-stat__label">رقم المرجع</span>
                            <div class="hr-side-stat__value">{{ $registration->reference_number ?: '—' }}</div>
                        </div>
                        <div class="hr-side-stat">
                            <span class="hr-side-stat__label">المؤشرات الطبية الإيجابية</span>
                            <div class="hr-side-stat__value">{{ $positiveFlags->count() }}</div>
                        </div>
                        <div class="hr-side-stat">
                            <span class="hr-side-stat__label">اكتمال المستندات</span>
                            <div class="hr-side-stat__value">
                                {{ $registration->hasDocuments() ? 'مكتملة' : 'ناقصة' }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="hr-panel">
                    <div class="hr-panel__head">
                        <h3 class="hr-panel__title">سجل القرار</h3>
                    </div>
                    <div class="hr-panel__body">
                        <div class="hr-rows">
                            <div class="hr-row">
                                <div class="hr-row__label">راجع بواسطة</div>
                                <div class="hr-row__value">{{ $registration->reviewer?->name ?: 'لم تُراجع بعد' }}</div>
                            </div>
                            <div class="hr-row">
                                <div class="hr-row__label">تاريخ المراجعة</div>
                                <div class="hr-row__value">{{ $registration->reviewed_at?->format('Y-m-d H:i') ?: '—' }}</div>
                            </div>
                            <div class="hr-row">
                                <div class="hr-row__label">الملاحظة</div>
                                <div class="hr-row__value">{{ $registration->review_note ?: 'لا توجد ملاحظة' }}</div>
                            </div>
                            <div class="hr-row">
                                <div class="hr-row__label">تاريخ الإنشاء</div>
                                <div class="hr-row__value">{{ $registration->created_at?->format('Y-m-d H:i') ?: '—' }}</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="hr-panel">
                    <div class="hr-panel__body">
                        <p class="hr-empty__text" style="margin: 0;">
                            استخدم أزرار <strong>اعتماد</strong> أو <strong>رفض</strong> أعلى الصفحة بعد مراجعة السجل الطبي والمستندات.
                        </p>
                    </div>
                </section>
            </aside>
        </div>

        <div
            x-cloak
            x-show="previewOpen"
            class="hr-modal"
            @click.self="closePreview()"
        >
            <div class="hr-modal__card" @click.stop>
                <div class="hr-modal__head">
                    <div class="hr-modal__title" x-text="previewTitle"></div>
                    <button type="button" class="hr-modal__close" @click="closePreview()">إغلاق</button>
                </div>
                <div class="hr-modal__body">
                    <template x-if="previewType === 'image'">
                        <img :src="previewUrl" alt="">
                    </template>
                    <template x-if="previewType !== 'image'">
                        <iframe :src="previewUrl" title="معاينة المستند"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
