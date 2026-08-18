@php
    $stepsMeta = [
        1 => ['label' => 'التحقق', 'desc' => 'الرقم الوطني'],
        2 => ['label' => 'البيانات', 'desc' => 'معلومات الموظف'],
        3 => ['label' => 'الطبي', 'desc' => 'السجل الصحي'],
        4 => ['label' => 'العائلة', 'desc' => 'المستفيدون'],
        5 => ['label' => 'المستندات', 'desc' => 'المرفقات'],
        6 => ['label' => 'المراجعة', 'desc' => 'تأكيد وإرسال'],
    ];
    $progress = round(($step / $totalSteps) * 100);
@endphp

<aside class="reg-sidebar">
    <div class="reg-sidebar-inner">
        {{-- Brand --}}
        <div class="mb-6 flex items-center gap-3 lg:mb-10">
            <div class="reg-brand-mark">
                <svg class="size-6 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/></svg>
            </div>
            <div>
                <p class="text-lg font-extrabold tracking-wide">مصلحة الضرائب</p>
                <p class="text-xs text-slate-400">التسجيل الطبي · SMART CARE</p>
            </div>
        </div>

        {{-- Mobile progress --}}
        <div class="reg-mobile-progress mb-5 lg:hidden">
            <div class="mb-2 flex items-center justify-between text-xs">
                <span class="font-bold text-teal-400">الخطوة {{ $step }} / {{ $totalSteps }}</span>
                <span class="text-slate-400">{{ $stepLabels[$step] ?? '' }}</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-white/10">
                <div class="h-full rounded-full bg-gradient-to-l from-teal-400 to-teal-500 transition-all duration-500" style="width: {{ $progress }}%"></div>
            </div>
        </div>

        {{-- Desktop step list --}}
        <nav class="hidden flex-1 flex-col gap-1 lg:flex" aria-label="خطوات التسجيل">
            @foreach ($stepsMeta as $num => $meta)
                @php
                    $isActive = $step === $num;
                    $isDone = $step > $num;
                @endphp
                <div @class([
                    'reg-step-item',
                    'reg-step-item-active' => $isActive,
                    'reg-step-item-done' => $isDone && ! $isActive,
                    'reg-step-item-pending' => ! $isActive && ! $isDone,
                ])>
                    <span @class([
                        'reg-step-num',
                        'bg-teal-500 text-white' => $isActive,
                        'bg-teal-500/20 text-teal-300' => $isDone && ! $isActive,
                        'bg-white/5 text-slate-500' => ! $isActive && ! $isDone,
                    ])>
                        @if ($isDone && ! $isActive)
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        @else
                            {{ $num }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-bold">{{ $meta['label'] }}</p>
                        <p class="truncate text-xs opacity-70">{{ $meta['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </nav>

        {{-- Footer actions --}}
        <div class="mt-auto pt-6">
            @if (! $submitted && ! $approvedLocked)
                <div class="mb-2 hidden justify-between text-xs text-slate-400 lg:flex">
                    <span>التقدم</span>
                    <span class="font-bold text-teal-400">{{ $progress }}%</span>
                </div>
                <div class="mb-5 hidden h-1.5 overflow-hidden rounded-full bg-white/10 lg:block">
                    <div class="h-full rounded-full bg-gradient-to-l from-teal-400 to-teal-500 transition-all duration-500" style="width: {{ $progress }}%"></div>
                </div>

                <button
                    type="button"
                    wire:click="clearForm"
                    wire:confirm="هل تريد مسح جميع البيانات والبدء من جديد؟"
                    wire:loading.attr="disabled"
                    wire:target="clearForm,submitRegistration,saveDraft,saveDocuments,saveEmployeeDetails,saveMedicalRecord,continueFromBeneficiaries,goBack,logout"
                    class="reg-btn-danger mt-0 hidden w-full text-xs lg:inline-flex"
                >
                    <span wire:loading.remove wire:target="clearForm" class="inline-flex items-center gap-2">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        مسح البيانات والبدء من جديد
                    </span>
                    <span wire:loading wire:target="clearForm" class="inline-flex items-center gap-2">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        جاري المسح…
                    </span>
                </button>
            @endif

            @if ($submitted || $approvedLocked)
                <button
                    type="button"
                    wire:click="logout"
                    wire:loading.attr="disabled"
                    wire:target="logout,editSubmittedRegistration,clearForm"
                    class="reg-btn-ghost w-full text-xs"
                >
                    <span wire:loading.remove wire:target="logout" class="inline-flex items-center gap-2">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9"/></svg>
                        تسجيل الخروج
                    </span>
                    <span wire:loading wire:target="logout" class="inline-flex items-center gap-2">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        جاري الخروج…
                    </span>
                </button>
            @endif
        </div>
    </div>
</aside>
