@php
    $maxUploadMb = \App\Support\RegistrationDocuments::maxMegabytes();
    $familyAccept = \App\Support\RegistrationDocuments::familyAcceptAttribute();
    $photoAccept = \App\Support\RegistrationDocuments::photoAcceptAttribute();
    $registration = $this->registration();
    $familyPreviewUrl = $registration
        ? \App\Support\RegistrationDocuments::url($registration, \App\Support\RegistrationDocuments::FAMILY_STATUS)
        : null;
    $familyIsImage = \App\Support\RegistrationDocuments::isImagePath($registration?->family_status_document_path);
    $employeePreviewUrl = $registration
        ? \App\Support\RegistrationDocuments::url($registration, \App\Support\RegistrationDocuments::EMPLOYEE_PHOTO)
        : null;
    $previewVersion = $registration?->updated_at?->timestamp ?? time();
@endphp

<section class="reg-card">
    <div class="reg-card-header">
        <h2 class="reg-card-title">إرفاق المستندات</h2>
        <p class="reg-card-subtitle">ارفع المستندات المطلوبة لإتمام التسجيل. الملفات الكبيرة تُرفع تدريجياً — لا تغلق الصفحة.</p>
    </div>

    <div class="space-y-5">
        <div
            @class(['reg-upload', 'reg-upload-done' => $hasFamilyDocument || $familyStatusDocument])
            x-data="{ uploading: false, progress: 0, error: false }"
            x-on:livewire-upload-start="uploading = true; error = false; progress = 0"
            x-on:livewire-upload-finish="uploading = false; progress = 100"
            x-on:livewire-upload-error="uploading = false; error = true; progress = 0"
            x-on:livewire-upload-cancel="uploading = false; progress = 0"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            wire:key="family-status-upload"
        >
            <input
                id="family-status-document"
                class="reg-upload-hit"
                type="file"
                accept="{{ $familyAccept }}"
                wire:model="familyStatusDocument"
                x-bind:disabled="uploading"
                aria-label="صورة من شهادة الوضع العائلي"
            >

            <div class="pointer-events-none relative z-[1]">
                <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-2xl bg-teal-100 text-teal-600">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 18H15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 15 4.5h-4.5A2.25 2.25 0 0 0 8.25 6.75v10.5A2.25 2.25 0 0 0 10.5 19.5Z"/></svg>
                </div>
                <p class="font-bold text-slate-800">صورة من شهادة الوضع العائلي <span class="reg-required">*</span></p>
                <p class="mt-1 text-xs text-slate-500">PDF أو صورة JPG/PNG/WEBP/HEIC — حد أقصى {{ $maxUploadMb }} م.ب</p>

                @if ($hasFamilyDocument && $familyIsImage && $familyPreviewUrl)
                    <div class="mx-auto mt-4 h-28 w-40 overflow-hidden rounded-xl ring-1 ring-teal-200">
                        <img src="{{ $familyPreviewUrl }}?v={{ $previewVersion }}" alt="معاينة شهادة الوضع العائلي" class="size-full object-cover">
                    </div>
                @endif

                @if ($hasFamilyDocument)
                    <p class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-teal-100 px-3 py-1 text-xs font-bold text-teal-700">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                        {{ $familyStatusDocumentName ?: 'تم الرفع' }}
                    </p>
                @endif

                <div x-show="uploading" x-cloak class="mx-auto mt-4 max-w-xs">
                    <div class="mb-1.5 flex items-center justify-between text-xs font-bold text-teal-700">
                        <span>جاري رفع ورقة العائلة…</span>
                        <span x-text="Math.round(progress) + '%'"></span>
                    </div>
                    <div class="reg-upload-meter" role="progressbar" :aria-valuenow="Math.round(progress)" aria-valuemin="0" aria-valuemax="100">
                        <div class="reg-upload-meter-bar" :style="'width: ' + progress + '%'"></div>
                    </div>
                    <p class="mt-2 text-[11px] font-medium text-slate-500">الاتصال بطيء؟ انتظر حتى اكتمال الشريط.</p>
                </div>

                <p x-show="error" x-cloak class="reg-field-error mt-3 justify-center">تعذر الرفع. جرّب ملفاً أصغر أو بصيغة PDF/JPG.</p>

                <span class="reg-btn-secondary mt-4 !inline-flex !w-auto pointer-events-none">
                    <span x-show="!uploading">{{ $hasFamilyDocument ? 'تغيير الملف' : 'اختيار ملف' }}</span>
                    <span x-show="uploading" x-cloak class="inline-flex items-center gap-2">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        جاري الرفع…
                    </span>
                </span>
            </div>
        </div>
        @error('familyStatusDocument') <p class="reg-field-error -mt-3 justify-center">{{ $message }}</p> @enderror

        <div
            @class(['reg-upload', 'reg-upload-done' => $hasEmployeePhoto || $employeePhoto])
            x-data="{ uploading: false, progress: 0, error: false }"
            x-on:livewire-upload-start="uploading = true; error = false; progress = 0"
            x-on:livewire-upload-finish="uploading = false; progress = 100"
            x-on:livewire-upload-error="uploading = false; error = true; progress = 0"
            x-on:livewire-upload-cancel="uploading = false; progress = 0"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            wire:key="employee-photo-upload"
        >
            <p class="font-bold text-slate-800">الصورة الشخصية للموظف <span class="reg-required">*</span></p>
            <p class="mt-1 text-xs text-slate-500">JPG أو PNG أو WEBP — حد أقصى {{ $maxUploadMb }} م.ب — مطلوبة لإصدار بطاقة التأمين</p>

            <div class="reg-photo-picker mt-4 !items-center">
                <div @class([
                    'reg-photo-preview',
                    'reg-photo-preview-filled' => $employeePhoto || $hasEmployeePhoto,
                ])>
                    @if ($employeePhoto)
                        <img src="{{ $employeePhoto->temporaryUrl() }}" alt="معاينة صورة الموظف" class="size-full object-cover">
                    @elseif ($hasEmployeePhoto && $employeePreviewUrl)
                        <img src="{{ $employeePreviewUrl }}?v={{ $previewVersion }}" alt="صورة الموظف" class="size-full object-cover">
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

                <label class="reg-btn-secondary relative mt-3 !min-h-11 w-full cursor-pointer overflow-hidden sm:!w-auto sm:min-w-[10rem]">
                    <input
                        class="reg-upload-hit"
                        type="file"
                        accept="{{ $photoAccept }}"
                        wire:model="employeePhoto"
                        x-bind:disabled="uploading"
                        aria-label="الصورة الشخصية للموظف"
                    >
                    <span x-show="!uploading" class="pointer-events-none">
                        {{ ($employeePhoto || $hasEmployeePhoto) ? 'تغيير الصورة' : 'اختيار صورة' }}
                    </span>
                    <span x-show="uploading" x-cloak class="pointer-events-none inline-flex items-center gap-2">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <span x-text="'جاري الرفع… ' + Math.round(progress) + '%'"></span>
                    </span>
                </label>
            </div>

            <div x-show="uploading" x-cloak class="mx-auto mt-4 max-w-xs">
                <div class="reg-upload-meter">
                    <div class="reg-upload-meter-bar" :style="'width: ' + progress + '%'"></div>
                </div>
            </div>

            <p x-show="error" x-cloak class="reg-field-error mt-3 justify-center">تعذر رفع الصورة. جرّب صورة أصغر بصيغة JPG أو PNG.</p>
        </div>
        @error('employeePhoto') <p class="reg-field-error -mt-3 justify-center">{{ $message }}</p> @enderror
    </div>
</section>

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'saveDocuments',
    'primaryLabel' => 'متابعة للمراجعة',
    'primaryTarget' => 'saveDocuments',
    'loadingLabel' => 'جاري الحفظ...',
])
