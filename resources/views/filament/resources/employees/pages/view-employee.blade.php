@php
    use App\Filament\Resources\MedicalRegistrations\MedicalRegistrationResource;

    $employee = $this->getRecord();
    $registrations = $employee->medicalRegistrations;
    $hasSubmitted = $this->hasSubmittedForm();
    $latestSubmitted = $employee->latestSubmittedRegistration;
@endphp

<x-filament-panels::page>
    <div dir="rtl" class="space-y-8">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-white/10 dark:bg-gray-900 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200' => $hasSubmitted,
                            'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200' => ! $hasSubmitted,
                        ])>
                            {{ $hasSubmitted ? 'أرسل النموذج' : 'لم يرسل النموذج' }}
                        </span>
                        <span @class([
                            'inline-flex rounded-full px-2.5 py-1 text-xs font-bold',
                            'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200' => $employee->is_active,
                            'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $employee->is_active,
                        ])>
                            {{ $employee->is_active ? 'موظف نشط' : 'غير نشط' }}
                        </span>
                    </div>

                    <div>
                        <h2 class="text-2xl font-extrabold tracking-tight text-gray-950 dark:text-white">
                            {{ $employee->full_name }}
                        </h2>
                        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ $employee->workplaceLabel() ?? 'مكان العمل غير محدد' }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:min-w-72">
                    <div class="rounded-xl bg-gray-50 px-3 py-3 dark:bg-white/5">
                        <div class="text-[11px] font-bold text-gray-500">الرقم الوظيفي</div>
                        <div class="mt-1 text-sm font-extrabold text-gray-950 dark:text-white">{{ $employee->employee_number }}</div>
                    </div>
                    <div class="rounded-xl bg-gray-50 px-3 py-3 dark:bg-white/5">
                        <div class="text-[11px] font-bold text-gray-500">الرقم الوطني</div>
                        <div class="mt-1 text-sm font-extrabold text-gray-950 dark:text-white">{{ $employee->national_id }}</div>
                    </div>
                </div>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-3 border-t border-gray-100 pt-5 dark:border-white/10 sm:grid-cols-4">
                <div>
                    <dt class="text-[11px] font-bold text-gray-500">تاريخ الميلاد</dt>
                    <dd class="mt-1 text-sm font-bold text-gray-950 dark:text-white">{{ $employee->date_of_birth?->format('Y-m-d') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold text-gray-500">عدد الطلبات</dt>
                    <dd class="mt-1 text-sm font-bold text-gray-950 dark:text-white">{{ $registrations->count() }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold text-gray-500">آخر حالة</dt>
                    <dd class="mt-1 text-sm font-bold text-gray-950 dark:text-white">
                        {{ $latestSubmitted?->status->label() ?? 'لا يوجد إرسال' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-[11px] font-bold text-gray-500">آخر إرسال</dt>
                    <dd class="mt-1 text-sm font-bold text-gray-950 dark:text-white">
                        {{ $latestSubmitted?->submitted_at?->format('Y-m-d H:i') ?: '—' }}
                    </dd>
                </div>
            </dl>
        </section>

        @unless ($hasSubmitted)
            <section class="flex items-start gap-3 rounded-2xl border border-dashed border-amber-300 bg-amber-50 px-4 py-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-300" />
                <div>
                    <p class="text-sm font-extrabold text-amber-900 dark:text-amber-100">هذا الموظف لم يرسل النموذج بعد</p>
                    <p class="mt-1 text-xs font-medium text-amber-800/80 dark:text-amber-200/80">
                        لن يظهر له طلب للمراجعة حتى يُكمل الإرسال من النموذج العام.
                    </p>
                </div>
            </section>
        @endunless

        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-extrabold text-gray-950 dark:text-white">سجل طلبات التسجيل</h3>
                <span class="text-xs font-bold text-gray-500">{{ $registrations->count() }} طلب</span>
            </div>

            @forelse ($registrations as $registration)
                <a
                    href="{{ MedicalRegistrationResource::getUrl('view', ['record' => $registration]) }}"
                    class="block rounded-2xl border border-gray-200 bg-white px-4 py-4 transition hover:border-primary-400 hover:shadow-sm dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-400"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-extrabold text-gray-950 dark:text-white">
                                    {{ $registration->reference_number ?: 'بدون مرجع' }}
                                </span>
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-[11px] font-bold',
                                    'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200' => $registration->status->value === 'submitted',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200' => $registration->status->value === 'approved',
                                    'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200' => $registration->status->value === 'declined',
                                    'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $registration->status->value === 'draft',
                                ])>
                                    {{ $registration->status->label() }}
                                </span>
                            </div>
                            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                الإرسال: {{ $registration->submitted_at?->format('Y-m-d H:i') ?: '—' }}
                                · الإنشاء: {{ $registration->created_at?->format('Y-m-d H:i') ?: '—' }}
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-1 text-sm font-bold text-primary-600 dark:text-primary-400">
                            فتح الملف
                            <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                        </span>
                    </div>
                </a>
            @empty
                <div class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-300 px-4 py-12 text-center dark:border-white/15">
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-8 w-8 text-gray-400" />
                    <p class="text-sm font-extrabold text-gray-700 dark:text-gray-200">لا توجد طلبات لهذا الموظف</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">عند بدء التعبئة أو الإرسال ستظهر الطلبات هنا.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
