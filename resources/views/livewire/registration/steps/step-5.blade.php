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
            data-reg-field="familyStatusDocument"
            @class([
                'reg-upload',
                'reg-upload-done' => $hasFamilyDocument || $familyStatusDocument,
                'reg-input-invalid' => $errors->has('familyStatusDocument'),
            ])
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

        <x-reg-photo-requirements title-id="employee-photo-requirements" />

        <div
            x-data="{ uploading: false, progress: 0, error: false }"
            x-on:livewire-upload-start="uploading = true; error = false; progress = 0"
            x-on:livewire-upload-finish="uploading = false; progress = 100"
            x-on:livewire-upload-error="uploading = false; error = true; progress = 0"
            x-on:livewire-upload-cancel="uploading = false; progress = 0"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            wire:key="employee-photo-upload"
        >
            <p class="reg-label">الصورة الشخصية للموظف <span class="reg-required">*</span></p>
            <p class="mt-1 text-xs text-slate-500">مطلوبة لإصدار بطاقة التأمين</p>

            @php
                $employeeHasPhoto = (bool) ($employeePhoto || $hasEmployeePhoto);
            @endphp
            <label
                data-reg-field="employeePhoto"
                @class([
                    'reg-photo-dropzone relative mt-3 overflow-hidden',
                    'reg-photo-dropzone-filled' => $employeeHasPhoto,
                    'reg-photo-dropzone-invalid' => $errors->has('employeePhoto'),
                ])
            >
                <input
                    class="reg-upload-hit"
                    type="file"
                    accept="{{ $photoAccept }}"
                    wire:model="employeePhoto"
                    x-bind:disabled="uploading"
                    aria-label="الصورة الشخصية للموظف"
                >

                @if ($employeePhoto)
                    <div class="reg-photo-dropzone-frame pointer-events-none">
                        <img src="{{ $employeePhoto->temporaryUrl() }}" alt="معاينة صورة الموظف" class="size-full object-cover">
                        <span class="reg-photo-badge">معاينة جديدة</span>
                    </div>
                @elseif ($hasEmployeePhoto && $employeePreviewUrl)
                    <div class="reg-photo-dropzone-frame pointer-events-none">
                        <img src="{{ $employeePreviewUrl }}?v={{ $previewVersion }}" alt="صورة الموظف" class="size-full object-cover">
                        <span class="reg-photo-badge">محفوظة</span>
                    </div>
                @elseif ($hasEmployeePhoto)
                    <div class="reg-photo-dropzone-frame pointer-events-none">
                        <span class="reg-photo-badge">محفوظة</span>
                    </div>
                @else
                    <div class="reg-photo-dropzone-icon pointer-events-none" aria-hidden="true">
                        <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                    </div>
                @endif

                <div class="reg-photo-dropzone-copy pointer-events-none">
                    <p class="reg-photo-dropzone-title">
                        <span x-show="!uploading">
                            {{ $employeeHasPhoto ? 'تم اختيار صورة الموظف' : 'اضغط هنا لاختيار الصورة الشخصية' }}
                        </span>
                        <span x-show="uploading" x-cloak>جاري رفع الصورة…</span>
                    </p>
                    <p class="reg-photo-dropzone-hint">{{ \App\Support\RegistrationDocuments::photoSizeHint() }} — الوجه واضح على خلفية بيضاء</p>
                    <span class="reg-photo-dropzone-cta">
                        <span x-show="!uploading">{{ $employeeHasPhoto ? 'تغيير الصورة' : 'اختيار صورة' }}</span>
                        <span x-show="uploading" x-cloak class="inline-flex items-center gap-2" x-text="'جاري الرفع… ' + Math.round(progress) + '%'"></span>
                    </span>
                </div>
            </label>

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
