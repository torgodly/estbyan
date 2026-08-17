<section class="reg-card">
    <div class="reg-card-header">
        <h2 class="reg-card-title">إرفاق المستندات</h2>
        <p class="reg-card-subtitle">ارفع الصورة الشخصية المطلوبة لإتمام التسجيل.</p>
    </div>

    <div class="space-y-5">
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
