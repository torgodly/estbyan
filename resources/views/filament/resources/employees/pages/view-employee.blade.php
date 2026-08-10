@php
    use App\Enums\RegistrationStatus;
    use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;

    $employee = $this->getRecord();
    $registrations = $employee->medicalRegistrations;
@endphp

<x-filament-panels::page>
    <div class="hr-dossier" dir="rtl">
        <div class="hr-dossier__sticky">
            <div class="hr-dossier__meta">
                <span class="hr-dossier__eyebrow">ملف الموظف</span>
                <h2 class="hr-dossier__title">{{ $employee->full_name }}</h2>
                <div class="hr-dossier__chips">
                    <span class="hr-dossier__chip">{{ $employee->employee_number }}</span>
                    <span class="hr-dossier__chip">{{ $employee->national_id }}</span>
                    <span @class([
                        'hr-dossier__chip',
                        'hr-dossier__chip--approved' => $employee->is_active,
                        'hr-dossier__chip--declined' => ! $employee->is_active,
                    ])>
                        {{ $employee->is_active ? 'نشط' : 'غير نشط' }}
                    </span>
                </div>
            </div>
        </div>

        <section class="hr-dossier__card" style="margin-bottom: 1rem;">
            <h3 class="hr-dossier__section-title">بيانات أساسية</h3>
            <div class="hr-dossier__identity-grid">
                <div>
                    <span class="hr-dossier__label">مكان العمل</span>
                    <strong>{{ $employee->workplaceLabel() ?? '—' }}</strong>
                </div>
                <div>
                    <span class="hr-dossier__label">تاريخ الميلاد</span>
                    <strong>{{ $employee->date_of_birth?->format('Y-m-d') ?: '—' }}</strong>
                </div>
                <div>
                    <span class="hr-dossier__label">عدد الطلبات</span>
                    <strong>{{ $registrations->count() }}</strong>
                </div>
                <div>
                    <span class="hr-dossier__label">ملخص الحالة</span>
                    <strong>
                        @if ($this->hasPendingRegistration())
                            يوجد طلب بانتظار المراجعة
                        @elseif ($this->hasApprovedRegistration())
                            يوجد طلب معتمد
                        @elseif ($registrations->isNotEmpty())
                            {{ $registrations->first()->status->label() }}
                        @else
                            لا توجد طلبات
                        @endif
                    </strong>
                </div>
            </div>
            <div class="hr-dossier__flags" style="margin-top: 0.9rem;">
                <span @class(['hr-dossier__flag', 'hr-dossier__flag--yes' => $this->hasPendingRegistration(), 'hr-dossier__flag--no' => ! $this->hasPendingRegistration()])>
                    بانتظار المراجعة
                </span>
                <span @class(['hr-dossier__flag', 'hr-dossier__flag--yes' => $this->hasApprovedRegistration(), 'hr-dossier__flag--no' => ! $this->hasApprovedRegistration()])>
                    معتمد
                </span>
            </div>
        </section>

        <section class="hr-dossier__card">
            <h3 class="hr-dossier__section-title">سجل طلبات التسجيل</h3>
            @if ($registrations->isEmpty())
                <p class="hr-dossier__empty">لم يُقدّم هذا الموظف أي طلب تسجيل بعد.</p>
            @else
                <div class="hr-employee-regs">
                    <div class="hr-employee-regs__head">
                        <span>المرجع</span>
                        <span>الحالة</span>
                        <span>تاريخ الإرسال</span>
                        <span></span>
                    </div>
                    @foreach ($registrations as $registration)
                        <div class="hr-employee-regs__row">
                            <strong>{{ $registration->reference_number ?: '—' }}</strong>
                            <span class="hr-dossier__chip hr-dossier__chip--{{ $registration->status->value }}" style="justify-self: start;">
                                {{ $registration->status->label() }}
                            </span>
                            <span>{{ $registration->submitted_at?->format('Y-m-d H:i') ?: '—' }}</span>
                            <a href="{{ MedicalRegistrationResource::getUrl('view', ['record' => $registration]) }}" class="hr-dossier__link">
                                فتح الملف
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
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
            margin-bottom: 1.25rem;
            padding: 1rem 1.15rem;
            border-radius: 1rem;
            background: linear-gradient(135deg, #0f2744 0%, #163660 55%, #0f766e 140%);
            color: #fff;
        }

        .hr-dossier__eyebrow {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.75rem;
            font-weight: 700;
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
            color: inherit;
        }

        .hr-dossier__chip--submitted { background: rgba(56, 189, 248, 0.25); color: #0f172a; }
        .hr-dossier__chip--approved { background: rgba(34, 197, 94, 0.28); color: #0f172a; }
        .hr-dossier__chip--declined { background: rgba(248, 113, 113, 0.28); color: #0f172a; }
        .hr-dossier__chip--draft { background: rgba(148, 163, 184, 0.28); color: #0f172a; }

        .hr-dossier__card {
            padding: 1.1rem;
            border: 1px solid var(--hr-border);
            border-radius: 1rem;
            background: #fff;
        }

        .hr-dossier__section-title {
            margin: 0 0 0.9rem;
            font-size: 1rem;
            font-weight: 800;
            color: var(--hr-navy);
        }

        .hr-dossier__identity-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.85rem 1rem;
        }

        .hr-dossier__label {
            display: block;
            margin-bottom: 0.2rem;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
        }

        .hr-dossier__identity-grid strong {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--hr-navy);
        }

        .hr-dossier__flags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
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

        .hr-dossier__link {
            color: var(--hr-teal);
            font-weight: 800;
            text-decoration: underline;
            text-underline-offset: 2px;
        }

        .hr-dossier__empty {
            margin: 0;
            color: #64748b;
            font-size: 0.9rem;
        }

        .hr-employee-regs {
            display: grid;
            gap: 0.55rem;
        }

        .hr-employee-regs__head,
        .hr-employee-regs__row {
            display: grid;
            grid-template-columns: 1.2fr 1fr 1.2fr auto;
            gap: 0.75rem;
            align-items: center;
        }

        .hr-employee-regs__head {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 800;
            padding: 0 0.25rem 0.35rem;
            border-bottom: 1px solid var(--hr-border);
        }

        .hr-employee-regs__row {
            padding: 0.75rem 0.85rem;
            border: 1px solid var(--hr-border);
            border-radius: 0.85rem;
            background: var(--hr-canvas);
        }

        .hr-employee-regs__row strong {
            color: var(--hr-navy);
        }

        @media (max-width: 800px) {
            .hr-dossier__identity-grid,
            .hr-employee-regs__head,
            .hr-employee-regs__row {
                grid-template-columns: 1fr;
            }

            .hr-employee-regs__head {
                display: none;
            }
        }
    </style>
</x-filament-panels::page>
