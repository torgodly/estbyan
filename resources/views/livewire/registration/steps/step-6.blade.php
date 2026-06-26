<section class="reg-card">
    <div class="reg-card-header">
        <h2 class="reg-card-title">مراجعة وإرسال</h2>
        <p class="reg-card-subtitle">راجع بياناتك بعناية قبل الإرسال النهائي.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="reg-review-block">
            <p class="reg-review-label">بيانات الموظف</p>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2 border-b border-slate-200/60 pb-2"><dt class="text-slate-500">الاسم</dt><dd class="font-bold text-slate-800">{{ $verifiedFullName }}</dd></div>
                <div class="flex justify-between gap-2 border-b border-slate-200/60 pb-2"><dt class="text-slate-500">الهاتف</dt><dd class="font-bold text-slate-800" dir="ltr">{{ $phone }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">المدينة</dt><dd class="font-bold text-slate-800">{{ $cities[$city] ?? '—' }}</dd></div>
            </dl>
        </div>

        <div class="reg-review-block">
            <p class="reg-review-label">السجل الطبي</p>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2"><dt class="text-slate-500">أمراض مزمنة</dt><dd class="font-bold {{ $hasChronicConditions ? 'text-amber-600' : 'text-teal-600' }}">{{ $hasChronicConditions ? 'نعم' : 'لا' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">أورام</dt><dd class="font-bold {{ $hasTumor ? 'text-amber-600' : 'text-teal-600' }}">{{ $hasTumor ? 'نعم' : 'لا' }}</dd></div>
                <div class="flex justify-between gap-2"><dt class="text-slate-500">عمليات جراحية</dt><dd class="font-bold {{ $hasSurgeryHistory ? 'text-amber-600' : 'text-teal-600' }}">{{ $hasSurgeryHistory ? 'نعم' : 'لا' }}</dd></div>
            </dl>
        </div>

        @if (count($beneficiaries))
            <div class="reg-review-block lg:col-span-2">
                <p class="reg-review-label">المستفيدون ({{ count($beneficiaries) }})</p>
                <div class="divide-y divide-slate-200/60">
                    @foreach ($beneficiaries as $b)
                        <div class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <span class="font-bold text-slate-800">{{ $b['full_name'] }}</span>
                            <span class="text-slate-500">{{ \App\Enums\BeneficiaryRelationship::from($b['relationship'])->label() }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="reg-review-block lg:col-span-2">
            <p class="reg-review-label">المستندات</p>
            <ul class="space-y-1.5 text-sm text-slate-700">
                @if ($hasFamilyDocument || $familyStatusDocument)
                    <li class="flex items-center gap-2"><svg class="size-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg> شهادة الوضع العائلي</li>
                @endif
                @if ($hasEmployeePhoto || $employeePhoto)
                    <li class="flex items-center gap-2"><svg class="size-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg> الصورة الشخصية</li>
                @endif
            </ul>
        </div>
    </div>

    @error('submit') <p class="reg-field-error mt-4">{{ $message }}</p> @enderror
</section>

<div class="reg-actions">
    <div class="reg-actions-inner">
        <button wire:click="submitRegistration" wire:loading.attr="disabled" class="reg-btn-primary lg:min-w-[12rem]">
            <span wire:loading.remove wire:target="submitRegistration">تأكيد وإرسال التسجيل</span>
            <span wire:loading wire:target="submitRegistration" class="flex items-center gap-2">
                <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                جاري الإرسال...
            </span>
        </button>
        <button wire:click="saveDraft" type="button" class="reg-btn-secondary lg:min-w-[10rem]">حفظ كمسودة</button>
        <button wire:click="goBack" type="button" class="reg-btn-secondary lg:min-w-[8rem]">رجوع</button>
    </div>
</div>
