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
            <p class="font-bold text-slate-800">صورة من شهادة الوضع العائلي <span class="reg-required">*</span></p>
            <p class="mt-1 text-xs text-slate-500">PDF أو صورة JPG/PNG — حد أقصى 10 م.ب</p>
            @if ($hasFamilyDocument && ! $familyStatusDocument)
                <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-teal-100 px-3 py-1 text-xs font-bold text-teal-700">
                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    تم الرفع مسبقاً
                </p>
            @endif
            <label class="reg-btn-secondary mt-4 cursor-pointer !inline-flex !w-auto">
                <span wire:loading.remove wire:target="familyStatusDocument">اختيار ملف</span>
                <span wire:loading wire:target="familyStatusDocument">جاري الرفع…</span>
                <input wire:model="familyStatusDocument" type="file" accept=".pdf,application/pdf,image/jpeg,image/png,image/jpg" class="sr-only">
            </label>
            @error('familyStatusDocument') <p class="reg-field-error mt-2 justify-center">{{ $message }}</p> @enderror
        </div>

        <div @class(['reg-upload', 'reg-upload-done' => $hasEmployeePhoto || $employeePhoto])>
            <p class="font-bold text-slate-800">الصورة الشخصية للموظف <span class="reg-required">*</span></p>
            <p class="mt-1 text-xs text-slate-500">JPG أو PNG — مطلوبة لإصدار بطاقة التأمين</p>

            <div class="reg-photo-picker mt-4 !items-center">
                <div @class([
                    'reg-photo-preview',
                    'reg-photo-preview-filled' => $employeePhoto || $hasEmployeePhoto,
                ])>
                    @if ($employeePhoto)
                        <img src="{{ $employeePhoto->temporaryUrl() }}" alt="معاينة صورة الموظف" class="size-full object-cover">
                    @elseif ($hasEmployeePhoto && $this->registration()?->employee_photo_path)
                        <img src="{{ \App\Support\RegistrationDocuments::url($this->registration(), \App\Support\RegistrationDocuments::EMPLOYEE_PHOTO) }}" alt="صورة الموظف" class="size-full object-cover">
                    @else
                        <div class="flex flex-col items-center gap-2 px-4 text-center">
                            <svg class="size-9 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                            <span class="text-xs text-slate-400">JPG أو PNG</span>
                        </div>
                    @endif

                    @if ($employeePhoto || $hasEmployeePhoto)
                        <span class="reg-photo-badge">
                            {{ $employeePhoto ? 'معاينة جديدة' : 'محفوظة' }}
                        </span>
                    @endif
                </div>

                <label class="reg-btn-secondary mt-3 !min-h-11 w-full cursor-pointer sm:!w-auto sm:min-w-[10rem]">
                    <span wire:loading.remove wire:target="employeePhoto">
                        {{ ($employeePhoto || $hasEmployeePhoto) ? 'تغيير الصورة' : 'اختيار صورة' }}
                    </span>
                    <span wire:loading wire:target="employeePhoto">جاري الرفع…</span>
                    <input wire:model="employeePhoto" type="file" accept="image/jpeg,image/png,image/jpg" class="sr-only">
                </label>
            </div>

            @error('employeePhoto') <p class="reg-field-error mt-2 justify-center">{{ $message }}</p> @enderror
        </div>
    </div>
</section>

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'saveDocuments',
    'primaryLabel' => 'متابعة للمراجعة',
    'primaryTarget' => 'saveDocuments',
    'loadingLabel' => 'جاري الحفظ...',
])
