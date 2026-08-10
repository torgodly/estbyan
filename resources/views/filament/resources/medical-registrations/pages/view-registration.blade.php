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
    $statusColor = match ($registration->status->value) {
        'submitted' => 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200',
        'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200',
        'declined' => 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-200',
        default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200',
    };
@endphp

<x-filament-panels::page>
    <div
        dir="rtl"
        class="space-y-8"
        x-data="{
            previewOpen: false,
            previewUrl: null,
            previewType: null,
            previewTitle: '',
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
        }"
        @keydown.escape.window="closePreview()"
    >
        {{-- Identity header --}}
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-6 p-5 sm:flex-row sm:items-center sm:p-6">
                <div class="shrink-0">
                    @if ($photoUrl)
                        <button
                            type="button"
                            @click="openPreview(@js($photoUrl), 'image', 'صورة الموظف')"
                            class="block overflow-hidden rounded-2xl ring-1 ring-gray-200 transition hover:ring-primary-400 dark:ring-white/10"
                        >
                            <img src="{{ $photoUrl }}" alt="" class="h-36 w-32 object-cover sm:h-40 sm:w-36">
                        </button>
                    @else
                        <div class="flex h-36 w-32 flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-300 bg-gray-50 text-gray-400 dark:border-white/15 dark:bg-white/5 sm:h-40 sm:w-36">
                            <x-filament::icon icon="heroicon-o-user" class="h-8 w-8" />
                            <span class="text-xs font-bold">لا توجد صورة</span>
                        </div>
                    @endif
                </div>

                <div class="min-w-0 flex-1 space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span @class(['inline-flex rounded-full px-2.5 py-1 text-xs font-bold', $statusColor])>
                            {{ $registration->status->label() }}
                        </span>
                        @if (filled($registration->reference_number))
                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                {{ $registration->reference_number }}
                            </span>
                        @endif
                    </div>

                    <div>
                        <h2 class="truncate text-2xl font-extrabold tracking-tight text-gray-950 dark:text-white">
                            {{ $registration->full_name ?: 'بدون اسم' }}
                        </h2>
                        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ $registration->workplaceLabel() ?? 'مكان العمل غير محدد' }}
                            @if ($registration->jobTitleLabel())
                                · {{ $registration->jobTitleLabel() }}
                            @endif
                        </p>
                    </div>

                    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3">
                        <div>
                            <dt class="text-[11px] font-bold text-gray-500 dark:text-gray-400">الرقم الوظيفي</dt>
                            <dd class="mt-0.5 text-sm font-bold text-gray-950 dark:text-white">{{ $registration->employee_number ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold text-gray-500 dark:text-gray-400">الرقم الوطني</dt>
                            <dd class="mt-0.5 text-sm font-bold text-gray-950 dark:text-white">{{ $registration->national_id ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-bold text-gray-500 dark:text-gray-400">تاريخ الإرسال</dt>
                            <dd class="mt-0.5 text-sm font-bold text-gray-950 dark:text-white">
                                {{ $registration->submitted_at?->format('Y-m-d H:i') ?: '—' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        {{-- Details --}}
        <section class="space-y-4">
            <h3 class="text-sm font-extrabold text-gray-950 dark:text-white">البيانات الشخصية والتواصل</h3>
            <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10">
                <dl class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ([
                        'تاريخ الميلاد' => $registration->date_of_birth?->format('Y-m-d') ?: '—',
                        'الجنس' => $registration->gender?->label() ?? '—',
                        'الحالة الاجتماعية' => $registration->marital_status?->label() ?? '—',
                        'الهاتف' => $registration->phone ?: '—',
                        'واتساب' => $registration->whatsapp ?: '—',
                        'البريد' => $registration->email ?: '—',
                        'المدينة' => $registration->cityLabel() ?? '—',
                        'العنوان' => $registration->address ?: '—',
                    ] as $label => $value)
                        <div class="grid grid-cols-1 gap-1 px-4 py-3 sm:grid-cols-[10rem_1fr] sm:items-center sm:gap-6">
                            <dt class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- Medical --}}
        <section class="space-y-4">
            <h3 class="text-sm font-extrabold text-gray-950 dark:text-white">السجل الطبي</h3>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                @foreach ($medicalFlags as $flag)
                    <div @class([
                        'rounded-xl border px-3 py-3',
                        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200' => $flag['value'],
                        'border-gray-200 bg-white text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300' => ! $flag['value'],
                    ])>
                        <div class="text-[11px] font-bold opacity-80">{{ $flag['label'] }}</div>
                        <div class="mt-1 text-sm font-extrabold">{{ $flag['value'] ? 'نعم' : 'لا' }}</div>
                    </div>
                @endforeach
            </div>
            @if ($chronicLabels->isNotEmpty())
                <div class="flex flex-wrap gap-2">
                    @foreach ($chronicLabels as $label)
                        <span class="rounded-lg bg-teal-50 px-2.5 py-1 text-xs font-bold text-teal-800 dark:bg-teal-500/15 dark:text-teal-200">
                            {{ $label }}
                        </span>
                    @endforeach
                </div>
            @else
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">لا توجد أمراض مزمنة محددة.</p>
            @endif
        </section>

        {{-- Documents --}}
        <section class="space-y-4">
            <h3 class="text-sm font-extrabold text-gray-950 dark:text-white">المستندات</h3>
            <div class="grid gap-3 md:grid-cols-2">
                <x-filament.document-preview
                    :url="$familyDocUrl"
                    title="شهادة الوضع العائلي"
                    empty-label="لم يُرفق هذا المستند"
                />
                <x-filament.document-preview
                    :url="$photoUrl"
                    title="صورة الموظف"
                    type="image"
                    empty-label="لم تُرفع صورة الموظف"
                />
            </div>
        </section>

        {{-- Beneficiaries --}}
        <section class="space-y-4">
            <div class="flex items-center justify-between gap-3">
                <h3 class="text-sm font-extrabold text-gray-950 dark:text-white">المستفيدون</h3>
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $registration->beneficiaries->count() }} مستفيد</span>
            </div>

            @forelse ($registration->beneficiaries as $beneficiary)
                @php
                    $beneficiaryPhoto = filled($beneficiary->photo_path)
                        ? Storage::disk('public')->url($beneficiary->photo_path)
                        : null;
                    $beneficiaryChronic = collect($beneficiary->chronic_conditions ?? [])
                        ->map(fn (string $key) => config('registration.chronic_conditions.'.$key) ?? $key)
                        ->filter()
                        ->values();
                @endphp
                <article class="rounded-2xl border border-gray-200 p-4 dark:border-white/10">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="shrink-0">
                            @if ($beneficiaryPhoto)
                                <button
                                    type="button"
                                    @click="openPreview(@js($beneficiaryPhoto), 'image', @js($beneficiary->full_name))"
                                    class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10"
                                >
                                    <img src="{{ $beneficiaryPhoto }}" alt="" class="h-20 w-20 object-cover">
                                </button>
                            @else
                                <div class="flex h-20 w-20 flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-gray-50 text-gray-400 dark:border-white/15 dark:bg-white/5">
                                    <x-filament::icon icon="heroicon-o-user" class="h-6 w-6" />
                                    <span class="mt-1 text-[10px] font-bold">بدون صورة</span>
                                </div>
                            @endif
                        </div>

                        <div class="min-w-0 flex-1 space-y-3">
                            <div>
                                <div class="text-base font-extrabold text-gray-950 dark:text-white">{{ $beneficiary->full_name }}</div>
                                <div class="text-xs font-bold text-gray-500 dark:text-gray-400">
                                    {{ $beneficiary->relationship?->label() ?? '—' }}
                                </div>
                            </div>

                            <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                <div>
                                    <dt class="text-[11px] font-bold text-gray-500">الرقم الوطني</dt>
                                    <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $beneficiary->national_id ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-bold text-gray-500">تاريخ الميلاد</dt>
                                    <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $beneficiary->date_of_birth?->format('Y-m-d') ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[11px] font-bold text-gray-500">فصيلة الدم</dt>
                                    <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $beneficiary->blood_type?->label() ?? '—' }}</dd>
                                </div>
                            </dl>

                            <div class="flex flex-wrap gap-1.5">
                                @foreach ([
                                    'مزمن' => (bool) $beneficiary->has_chronic_conditions,
                                    'ورم' => (bool) $beneficiary->has_tumor,
                                    'عملية' => (bool) $beneficiary->has_surgery_history,
                                    'جهاز' => (bool) $beneficiary->uses_medical_devices,
                                ] as $label => $value)
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-[11px] font-bold',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200' => $value,
                                        'bg-gray-100 text-gray-500 dark:bg-white/10 dark:text-gray-400' => ! $value,
                                    ])>
                                        {{ $label }}: {{ $value ? 'نعم' : 'لا' }}
                                    </span>
                                @endforeach
                            </div>

                            @if ($beneficiaryChronic->isNotEmpty())
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($beneficiaryChronic as $label)
                                        <span class="rounded-md bg-teal-50 px-2 py-0.5 text-[11px] font-bold text-teal-800 dark:bg-teal-500/15 dark:text-teal-200">
                                            {{ $label }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-dashed border-gray-300 px-4 py-10 text-center dark:border-white/15">
                    <x-filament::icon icon="heroicon-o-user-group" class="h-8 w-8 text-gray-400" />
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-200">لا يوجد مستفيدون على هذا الطلب</p>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">سيظهرون هنا عند إضافتهم من النموذج.</p>
                </div>
            @endforelse
        </section>

        {{-- Review --}}
        <section class="space-y-4">
            <h3 class="text-sm font-extrabold text-gray-950 dark:text-white">سجل المراجعة</h3>
            <div class="overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10">
                <dl class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ([
                        'تاريخ الإنشاء' => $registration->created_at?->format('Y-m-d H:i') ?: '—',
                        'تاريخ الإرسال' => $registration->submitted_at?->format('Y-m-d H:i') ?: '—',
                        'راجع بواسطة' => $registration->reviewer?->name ?: '—',
                        'تاريخ المراجعة' => $registration->reviewed_at?->format('Y-m-d H:i') ?: '—',
                        'ملاحظة المراجعة' => $registration->review_note ?: 'لا توجد ملاحظة',
                    ] as $label => $value)
                        <div class="grid grid-cols-1 gap-1 px-4 py-3 sm:grid-cols-[10rem_1fr] sm:items-center sm:gap-6">
                            <dt class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                            <dd class="text-sm font-semibold text-gray-950 dark:text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>

        {{-- Inline preview modal --}}
        <div
            x-cloak
            x-show="previewOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/70 p-4 backdrop-blur-sm"
            @click.self="closePreview()"
        >
            <div
                class="flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl dark:bg-gray-900"
                @click.stop
            >
                <div class="flex items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <div class="truncate text-sm font-extrabold text-gray-950 dark:text-white" x-text="previewTitle"></div>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-sm font-bold text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/10"
                        @click="closePreview()"
                    >
                        إغلاق
                        <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                    </button>
                </div>
                <div class="min-h-0 flex-1 bg-gray-50 p-3 dark:bg-black/20">
                    <template x-if="previewType === 'image'">
                        <img :src="previewUrl" alt="" class="mx-auto max-h-[75vh] rounded-xl object-contain">
                    </template>
                    <template x-if="previewType !== 'image'">
                        <iframe :src="previewUrl" class="h-[75vh] w-full rounded-xl bg-white" title="معاينة المستند"></iframe>
                    </template>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
