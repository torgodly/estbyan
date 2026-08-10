@php
    use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;

    $employee = $this->getRecord();
    $registrations = $employee->medicalRegistrations;
    $hasSubmitted = $this->hasSubmittedForm();
    $latestSubmitted = $employee->latestSubmittedRegistration;
@endphp

<x-filament-panels::page>
    <div dir="rtl" class="hr-review">
        <div class="hr-review__main">
            <section class="hr-panel">
                <div class="hr-panel__body">
                    <div class="hr-chips">
                        <span class="hr-chip {{ $hasSubmitted ? 'hr-chip--approved' : 'hr-chip--declined' }}">
                            {{ $hasSubmitted ? 'أرسل النموذج' : 'لم يرسل النموذج' }}
                        </span>
                        <span class="hr-chip {{ $employee->is_active ? 'hr-chip--submitted' : 'hr-chip--draft' }}">
                            {{ $employee->is_active ? 'موظف نشط' : 'غير نشط' }}
                        </span>
                    </div>

                    <h2 class="hr-hero__name">{{ $employee->full_name }}</h2>
                    <p class="hr-hero__sub">{{ $employee->workplaceLabel() ?? 'مكان العمل غير محدد' }}</p>

                    <div class="hr-kpis">
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">الرقم الوظيفي</span>
                            <div class="hr-kpi__value">{{ $employee->employee_number }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">الرقم الوطني</span>
                            <div class="hr-kpi__value">{{ $employee->national_id }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">تاريخ الميلاد</span>
                            <div class="hr-kpi__value">{{ $employee->date_of_birth?->format('Y-m-d') ?: '—' }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">عدد الطلبات</span>
                            <div class="hr-kpi__value">{{ $registrations->count() }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">آخر حالة</span>
                            <div class="hr-kpi__value">{{ $latestSubmitted?->status->label() ?? 'لا يوجد إرسال' }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">آخر إرسال</span>
                            <div class="hr-kpi__value">{{ $latestSubmitted?->submitted_at?->format('Y-m-d H:i') ?: '—' }}</div>
                        </div>
                    </div>
                </div>
            </section>

            @unless ($hasSubmitted)
                <div class="hr-alert">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0" />
                    <div>
                        <p class="hr-alert__title">هذا الموظف لم يرسل النموذج بعد</p>
                        <p class="hr-alert__text">لن يظهر له طلب للمراجعة حتى يُكمل الإرسال من النموذج العام.</p>
                    </div>
                </div>
            @endunless

            <section class="hr-panel">
                <div class="hr-panel__head">
                    <h3 class="hr-panel__title">سجل طلبات التسجيل</h3>
                    <span class="hr-panel__meta">{{ $registrations->count() }} طلب</span>
                </div>
                <div class="hr-panel__body">
                    @forelse ($registrations as $registration)
                        <a
                            href="{{ MedicalRegistrationResource::getUrl('view', ['record' => $registration]) }}"
                            class="hr-ben__item"
                            style="{{ ! $loop->first ? 'margin-top: 0.75rem; text-decoration: none;' : 'text-decoration: none;' }}"
                        >
                            <div style="grid-column: 1 / -1; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                                <div>
                                    <div class="hr-chips" style="margin-bottom: 0.35rem;">
                                        <span class="hr-chip">{{ $registration->reference_number ?: 'بدون مرجع' }}</span>
                                        <span class="hr-chip hr-chip--{{ $registration->status->value }}">
                                            {{ $registration->status->label() }}
                                        </span>
                                    </div>
                                    <p class="hr-hero__sub" style="margin: 0;">
                                        الإرسال: {{ $registration->submitted_at?->format('Y-m-d H:i') ?: '—' }}
                                        · الإنشاء: {{ $registration->created_at?->format('Y-m-d H:i') ?: '—' }}
                                    </p>
                                </div>
                                <span class="hr-doc__action">فتح الملف</span>
                            </div>
                        </a>
                    @empty
                        <div class="hr-empty">
                            <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-7 w-7" />
                            <p class="hr-empty__title">لا توجد طلبات لهذا الموظف</p>
                            <p class="hr-empty__text">عند بدء التعبئة أو الإرسال ستظهر الطلبات هنا.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
