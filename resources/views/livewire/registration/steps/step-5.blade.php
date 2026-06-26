<section class="reg-card">
    <div class="reg-card-header">
        <h2 class="reg-card-title">إرفاق المستندات</h2>
        <p class="reg-card-subtitle">ارفع المستندات المطلوبة لإتمام التسجيل.</p>
    </div>

    <div class="space-y-5">
        <div @class(['reg-upload', 'reg-upload-done' => $hasFamilyDocument || $familyStatusDocument])>
            <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-teal-100 text-teal-600">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 18H15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 15 4.5h-4.5A2.25 2.25 0 0 0 8.25 6.75v10.5A2.25 2.25 0 0 0 10.5 19.5Z"/></svg>
            </div>
            <p class="font-bold text-slate-800">شهادة الوضع العائلي <span class="reg-required">*</span></p>
            <p class="mt-1 text-xs text-slate-500">PDF فقط — حد أقصى 5 م.ب</p>
            @if ($hasFamilyDocument && ! $familyStatusDocument)
                <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-teal-100 px-3 py-1 text-xs font-bold text-teal-700">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    تم الرفع مسبقاً
                </p>
            @endif
            <label class="reg-btn-secondary mt-4 cursor-pointer !inline-flex !w-auto">
                <span>اختيار ملف PDF</span>
                <input wire:model="familyStatusDocument" type="file" accept=".pdf,application/pdf" class="sr-only">
            </label>
            @error('familyStatusDocument') <p class="reg-field-error mt-2 justify-center">{{ $message }}</p> @enderror
            <div wire:loading wire:target="familyStatusDocument" class="mt-2 text-xs font-bold text-teal-600">جاري الرفع...</div>
        </div>

        <div @class(['reg-upload', 'reg-upload-done' => $hasEmployeePhoto || $employeePhoto])>
            <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-600">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
            </div>
            <p class="font-bold text-slate-800">الصورة الشخصية للموظف</p>
            <p class="mt-1 text-xs text-slate-500">JPG أو PNG — لإصدار بطاقة التأمين</p>
            @if ($hasEmployeePhoto && ! $employeePhoto)
                <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-teal-100 px-3 py-1 text-xs font-bold text-teal-700">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    تم الرفع مسبقاً
                </p>
            @endif
            <label class="reg-btn-secondary mt-4 cursor-pointer !inline-flex !w-auto">
                <span>اختيار صورة</span>
                <input wire:model="employeePhoto" type="file" accept="image/jpeg,image/png,image/jpg" class="sr-only">
            </label>
            @error('employeePhoto') <p class="reg-field-error mt-2 justify-center">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'saveDocuments',
    'primaryLabel' => 'متابعة للمراجعة',
    'primaryLoading' => true,
    'primaryTarget' => 'saveDocuments',
])
