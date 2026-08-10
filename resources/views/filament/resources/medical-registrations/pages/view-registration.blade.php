@php
    use Illuminate\Support\Facades\Storage;

    $registration = $this->getRecord();
    $photoUrl = filled($registration->employee_photo_path)
        ? Storage::disk('public')->url($registration->employee_photo_path)
        : null;
    $familyDocUrl = filled($registration->family_status_document_path)
        ? Storage::disk('public')->url($registration->family_status_document_path)
        : null;
    $chronicLabels = collect($registration->chronic_conditions ?? [])
        ->map(fn (string $key) => config('registration.chronic_conditions.'.$key) ?? $key)
        ->filter()
        ->values();
    $medicalFlags = [
        ['label' => 'أمراض مزمنة', 'value' => (bool) $registration->has_chronic_conditions],
        ['label' => 'أورام', 'value' => (bool) $registration->has_tumor],
        ['label' => 'عمليات سابقة', 'value' => (bool) $registration->has_surgery_history],
        ['label' => 'أجهزة طبية', 'value' => (bool) $registration->uses_medical_devices],
        ['label' => 'إقامة مستشفى', 'value' => (bool) $registration->hospitalized_recently],
        ['label' => 'علاج بالخارج', 'value' => (bool) $registration->traveled_for_treatment],
    ];
@endphp

<x-filament-panels::page>
    <div class="hr-dossier" dir="rtl">
        <div class="hr-dossier__sticky">
            <div class="hr-dossier__sticky-main">
                <div class="hr-dossier__meta">
                    <span class="hr-dossier__eyebrow">ملف التسجيل الطبي</span>
                    <h2 class="hr-dossier__title">{{ $registration->full_name }}</h2>
                    <div class="hr-dossier__chips">
                        @if (filled($registration->reference_number))
                            <span class="hr-dossier__chip">{{ $registration->reference_number }}</span>
                        @endif
                        <span class="hr-dossier__chip hr-dossier__chip--{{ $registration->status->value }}">
                            {{ $registration->status->label() }}
                        </span>
                        @if ($registration->submitted_at)
                            <span class="hr-dossier__chip hr-dossier__chip--muted">
                                أُرسل {{ $registration->submitted_at->format('Y-m-d H:i') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <section class="hr-dossier__identity">
            <div class="hr-dossier__photo-wrap">
                @if ($photoUrl)
                    <img src="{{ $photoUrl }}" alt="صورة {{ $registration->full_name }}" class="hr-dossier__photo">
                @else
                    <div class="hr-dossier__photo hr-dossier__photo--empty">بدون صورة</div>
                @endif
            </div>
            <div class="hr-dossier__identity-grid">
                <div>
                    <span class="hr-dossier__label">الرقم الوظيفي</span>
                    <strong>{{ $registration->employee_number ?: '—' }}</strong>
                </div>
                <div>
                    <span class="hr-dossier__label">الرقم الوطني</span>
                    <strong>{{ $registration->national_id ?: '—' }}</strong>
                </div>
                <div>
                    <span class="hr-dossier__label">تاريخ الميلاد</span>
                    <strong>{{ $registration->date_of_birth?->format('Y-m-d') ?: '—' }}</strong>
                </div>
                <div>
                    <span class="hr-dossier__label">الجنس</span>
                    <strong>{{ $registration->gender?->label() ?? '—' }}</strong>
                </div>
                <div>
                    <span class="hr-dossier__label">الحالة الاجتماعية</span>
                    <strong>{{ $registration->marital_status?->label() ?? '—' }}</strong>
                </div>
                <div>
                    <span class="hr-dossier__label">المسمى الوظيفي</span>
                    <strong>{{ $registration->jobTitleLabel() ?? '—' }}</strong>
                </div>
            </div>
        </section>

        <div class="hr-dossier__sections">
            <section class="hr-dossier__card">
                <h3 class="hr-dossier__section-title">التواصل ومكان العمل</h3>
                <div class="hr-dossier__identity-grid">
                    <div>
                        <span class="hr-dossier__label">الهاتف</span>
                        <strong>{{ $registration->phone ?: '—' }}</strong>
                    </div>
                    <div>
                        <span class="hr-dossier__label">واتساب</span>
                        <strong>{{ $registration->whatsapp ?: '—' }}</strong>
                    </div>
                    <div>
                        <span class="hr-dossier__label">البريد</span>
                        <strong>{{ $registration->email ?: '—' }}</strong>
                    </div>
                    <div>
                        <span class="hr-dossier__label">مكان العمل</span>
                        <strong>{{ $registration->workplaceLabel() ?? '—' }}</strong>
                    </div>
                    <div>
                        <span class="hr-dossier__label">المدينة</span>
                        <strong>{{ $registration->cityLabel() ?? '—' }}</strong>
                    </div>
                    <div class="hr-dossier__span-2">
                        <span class="hr-dossier__label">العنوان</span>
                        <strong>{{ $registration->address ?: '—' }}</strong>
                    </div>
                </div>
            </section>

            <section class="hr-dossier__card">
                <h3 class="hr-dossier__section-title">السجل الطبي</h3>
                <div class="hr-dossier__flags">
                    @foreach ($medicalFlags as $flag)
                        <span @class([
                            'hr-dossier__flag',
                            'hr-dossier__flag--yes' => $flag['value'],
                            'hr-dossier__flag--no' => ! $flag['value'],
                        ])>
                            {{ $flag['label'] }}: {{ $flag['value'] ? 'نعم' : 'لا' }}
                        </span>
                    @endforeach
                </div>
                @if ($chronicLabels->isNotEmpty())
                    <div class="hr-dossier__tags">
                        @foreach ($chronicLabels as $label)
                            <span class="hr-dossier__tag">{{ $label }}</span>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="hr-dossier__card">
                <h3 class="hr-dossier__section-title">المستندات</h3>
                <div class="hr-dossier__docs">
                    <div class="hr-dossier__doc">
                        <span class="hr-dossier__label">شهادة الوضع العائلي</span>
                        @if ($familyDocUrl)
                            <a href="{{ $familyDocUrl }}" target="_blank" rel="noopener" class="hr-dossier__link">فتح المستند</a>
                        @else
                            <strong class="hr-dossier__missing">غير مرفوع</strong>
                        @endif
                    </div>
                    <div class="hr-dossier__doc">
                        <span class="hr-dossier__label">صورة الموظف</span>
                        @if ($photoUrl)
                            <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="hr-dossier__link">فتح الصورة</a>
                        @else
                            <strong class="hr-dossier__missing">غير مرفوعة</strong>
                        @endif
                    </div>
                </div>
            </section>

            <section class="hr-dossier__card hr-dossier__card--wide">
                <h3 class="hr-dossier__section-title">المستفيدون ({{ $registration->beneficiaries->count() }})</h3>
                @if ($registration->beneficiaries->isEmpty())
                    <p class="hr-dossier__empty">لا يوجد مستفيدون مسجّلون على هذا الطلب.</p>
                @else
                    <div class="hr-dossier__beneficiaries">
                        @foreach ($registration->beneficiaries as $beneficiary)
                            @php
                                $beneficiaryPhoto = filled($beneficiary->photo_path)
                                    ? Storage::disk('public')->url($beneficiary->photo_path)
                                    : null;
                                $beneficiaryChronic = collect($beneficiary->chronic_conditions ?? [])
                                    ->map(fn (string $key) => config('registration.chronic_conditions.'.$key) ?? $key)
                                    ->filter()
                                    ->values();
                            @endphp
                            <article class="hr-dossier__beneficiary">
                                <div class="hr-dossier__beneficiary-head">
                                    @if ($beneficiaryPhoto)
                                        <img src="{{ $beneficiaryPhoto }}" alt="" class="hr-dossier__beneficiary-photo">
                                    @else
                                        <div class="hr-dossier__beneficiary-photo hr-dossier__beneficiary-photo--empty"></div>
                                    @endif
                                    <div>
                                        <strong>{{ $beneficiary->full_name }}</strong>
                                        <span>{{ $beneficiary->relationship?->label() ?? '—' }}</span>
                                    </div>
                                </div>
                                <div class="hr-dossier__identity-grid hr-dossier__identity-grid--compact">
                                    <div>
                                        <span class="hr-dossier__label">الرقم الوطني</span>
                                        <strong>{{ $beneficiary->national_id ?: '—' }}</strong>
                                    </div>
                                    <div>
                                        <span class="hr-dossier__label">تاريخ الميلاد</span>
                                        <strong>{{ $beneficiary->date_of_birth?->format('Y-m-d') ?: '—' }}</strong>
                                    </div>
                                    <div>
                                        <span class="hr-dossier__label">فصيلة الدم</span>
                                        <strong>{{ $beneficiary->blood_type?->label() ?? '—' }}</strong>
                                    </div>
                                </div>
                                <div class="hr-dossier__flags hr-dossier__flags--compact">
                                    <span @class(['hr-dossier__flag', 'hr-dossier__flag--yes' => $beneficiary->has_chronic_conditions, 'hr-dossier__flag--no' => ! $beneficiary->has_chronic_conditions])>مزمن</span>
                                    <span @class(['hr-dossier__flag', 'hr-dossier__flag--yes' => $beneficiary->has_tumor, 'hr-dossier__flag--no' => ! $beneficiary->has_tumor])>ورم</span>
                                    <span @class(['hr-dossier__flag', 'hr-dossier__flag--yes' => $beneficiary->has_surgery_history, 'hr-dossier__flag--no' => ! $beneficiary->has_surgery_history])>عملية</span>
                                    <span @class(['hr-dossier__flag', 'hr-dossier__flag--yes' => $beneficiary->uses_medical_devices, 'hr-dossier__flag--no' => ! $beneficiary->uses_medical_devices])>جهاز</span>
                                </div>
                                @if ($beneficiaryChronic->isNotEmpty())
                                    <div class="hr-dossier__tags">
                                        @foreach ($beneficiaryChronic as $label)
                                            <span class="hr-dossier__tag">{{ $label }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="hr-dossier__card">
                <h3 class="hr-dossier__section-title">سجل المراجعة</h3>
                <div class="hr-dossier__timeline">
                    <div class="hr-dossier__timeline-item">
                        <span class="hr-dossier__label">تاريخ الإنشاء</span>
                        <strong>{{ $registration->created_at?->format('Y-m-d H:i') ?: '—' }}</strong>
                    </div>
                    <div class="hr-dossier__timeline-item">
                        <span class="hr-dossier__label">تاريخ الإرسال</span>
                        <strong>{{ $registration->submitted_at?->format('Y-m-d H:i') ?: '—' }}</strong>
                    </div>
                    <div class="hr-dossier__timeline-item">
                        <span class="hr-dossier__label">راجع بواسطة</span>
                        <strong>{{ $registration->reviewer?->name ?: '—' }}</strong>
                    </div>
                    <div class="hr-dossier__timeline-item">
                        <span class="hr-dossier__label">تاريخ المراجعة</span>
                        <strong>{{ $registration->reviewed_at?->format('Y-m-d H:i') ?: '—' }}</strong>
                    </div>
                    <div class="hr-dossier__timeline-item hr-dossier__span-2">
                        <span class="hr-dossier__label">ملاحظة المراجعة</span>
                        <strong>{{ $registration->review_note ?: '—' }}</strong>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <style>
        .hr-dossier {
            --hr-navy: #0f2744;
            --hr-teal: #0d9488;
            --hr-teal-soft: #ccfbf1;
            --hr-canvas: #f8fafc;
            --hr-border: #e2e8f0;
            color: #0f172a;
        }

        .hr-dossier__sticky {
            position: sticky;
            top: 0;
            z-index: 10;
            margin: -0.25rem 0 1.25rem;
            padding: 1rem 1.15rem;
            border: 1px solid var(--hr-border);
            border-radius: 1rem;
            background: linear-gradient(135deg, #0f2744 0%, #163660 55%, #0f766e 140%);
            color: #fff;
            box-shadow: 0 16px 40px -28px rgba(15, 39, 68, 0.75);
        }

        .hr-dossier__eyebrow {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #99f6e4;
        }

        .hr-dossier__title {
            margin: 0;
            font-size: 1.35rem;
            font-weight: 800;
        }

        .hr-dossier__chips {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
            margin-top: 0.75rem;
        }

        .hr-dossier__chip {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 0.2rem 0.7rem;
            font-size: 0.75rem;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
        }

        .hr-dossier__chip--submitted { background: rgba(56, 189, 248, 0.25); }
        .hr-dossier__chip--approved { background: rgba(34, 197, 94, 0.28); }
        .hr-dossier__chip--declined { background: rgba(248, 113, 113, 0.28); }
        .hr-dossier__chip--draft { background: rgba(148, 163, 184, 0.28); }
        .hr-dossier__chip--muted { opacity: 0.9; }

        .hr-dossier__identity {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 1.25rem;
            align-items: start;
            margin-bottom: 1.25rem;
            padding: 1.15rem;
            border: 1px solid var(--hr-border);
            border-radius: 1rem;
            background: #fff;
        }

        .hr-dossier__photo {
            width: 120px;
            height: 140px;
            object-fit: cover;
            border-radius: 0.9rem;
            border: 1px solid var(--hr-border);
            background: var(--hr-canvas);
        }

        .hr-dossier__photo--empty {
            display: grid;
            place-items: center;
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .hr-dossier__identity-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem 1rem;
        }

        .hr-dossier__identity-grid--compact {
            margin-top: 0.75rem;
        }

        .hr-dossier__label {
            display: block;
            margin-bottom: 0.2rem;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .hr-dossier__identity-grid strong,
        .hr-dossier__timeline-item strong,
        .hr-dossier__doc strong {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--hr-navy);
        }

        .hr-dossier__sections {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }

        .hr-dossier__card {
            padding: 1.1rem;
            border: 1px solid var(--hr-border);
            border-radius: 1rem;
            background: #fff;
        }

        .hr-dossier__card--wide {
            grid-column: 1 / -1;
        }

        .hr-dossier__section-title {
            margin: 0 0 0.9rem;
            font-size: 1rem;
            font-weight: 800;
            color: var(--hr-navy);
        }

        .hr-dossier__flags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .hr-dossier__flags--compact {
            margin-top: 0.75rem;
        }

        .hr-dossier__flag {
            border-radius: 999px;
            padding: 0.2rem 0.65rem;
            font-size: 0.72rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .hr-dossier__flag--yes {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }

        .hr-dossier__flag--no {
            background: #f8fafc;
            color: #64748b;
            border-color: #e2e8f0;
        }

        .hr-dossier__tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.4rem;
            margin-top: 0.85rem;
        }

        .hr-dossier__tag {
            border-radius: 0.55rem;
            padding: 0.25rem 0.55rem;
            background: var(--hr-teal-soft);
            color: #115e59;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .hr-dossier__docs {
            display: grid;
            gap: 0.85rem;
        }

        .hr-dossier__link {
            color: var(--hr-teal);
            font-weight: 800;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .hr-dossier__missing {
            color: #b91c1c !important;
        }

        .hr-dossier__beneficiaries {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 0.85rem;
        }

        .hr-dossier__beneficiary {
            padding: 0.9rem;
            border: 1px solid var(--hr-border);
            border-radius: 0.9rem;
            background: var(--hr-canvas);
        }

        .hr-dossier__beneficiary-head {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }

        .hr-dossier__beneficiary-head strong {
            display: block;
            color: var(--hr-navy);
        }

        .hr-dossier__beneficiary-head span {
            color: #64748b;
            font-size: 0.8rem;
            font-weight: 700;
        }

        .hr-dossier__beneficiary-photo {
            width: 52px;
            height: 52px;
            border-radius: 999px;
            object-fit: cover;
            border: 1px solid var(--hr-border);
            background: #fff;
        }

        .hr-dossier__beneficiary-photo--empty {
            background: #e2e8f0;
        }

        .hr-dossier__timeline {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem 1rem;
        }

        .hr-dossier__span-2 {
            grid-column: 1 / -1;
        }

        .hr-dossier__empty {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
        }

        @media (max-width: 900px) {
            .hr-dossier__identity,
            .hr-dossier__sections,
            .hr-dossier__identity-grid,
            .hr-dossier__timeline {
                grid-template-columns: 1fr;
            }

            .hr-dossier__photo-wrap {
                justify-self: start;
            }
        }
    </style>
</x-filament-panels::page>
