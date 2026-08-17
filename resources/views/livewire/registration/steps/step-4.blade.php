@php
    $beneficiaryMedicalQuestions = [
        ['prop' => 'beneficiaryHasTumor', 'ar' => 'هل يعاني من أورام؟'],
        ['prop' => 'beneficiaryHasSurgeryHistory', 'ar' => 'هل خضع لعمليات جراحية سابقة؟'],
        ['prop' => 'beneficiaryUsesMedicalDevices', 'ar' => 'هل يستخدم أجهزة أو مستلزمات طبية؟'],
        ['prop' => 'beneficiaryHospitalizedRecently', 'ar' => 'هل أُدخل المستشفى خلال الـ 12 شهراً الماضية؟'],
        ['prop' => 'beneficiaryTraveledForTreatment', 'ar' => 'هل سبق السفر للعلاج بالخارج؟'],
    ];
@endphp

<section class="space-y-5">
    <div class="reg-card">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="reg-card-title">المستفيدون</h2>
                <p class="reg-card-subtitle !mt-1">
                    @if ($maritalStatus === 'married')
                        أضف الزوج/الزوجة والأبناء والوالدين المشمولين بالتغطية
                    @else
                        أضف الوالدين المشمولين بالتغطية
                    @endif
                </p>
            </div>
            @if (count($beneficiaries) > 0 && ! $showBeneficiaryForm)
                <button wire:click="toggleBeneficiaryForm" type="button" class="reg-btn-primary shrink-0 sm:!w-auto sm:min-w-[10rem]">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    إضافة مستفيد
                </button>
            @elseif ($showBeneficiaryForm && count($beneficiaries) > 0)
                <button wire:click="toggleBeneficiaryForm" type="button" class="reg-btn-secondary shrink-0 sm:!w-auto sm:min-w-[10rem]">
                    إلغاء
                </button>
            @endif
        </div>
    </div>

    @if ($showBeneficiaryForm)
        <div class="reg-card border-2 border-dashed border-teal-200 bg-teal-50/20">
            <div class="mb-4 flex items-center justify-between gap-3">
                <h3 class="text-sm font-extrabold text-teal-800">{{ $editingBeneficiaryIndex !== null ? 'تعديل مستفيد' : 'مستفيد جديد' }}</h3>
                @if (count($beneficiaries) === 0)
                    <button wire:click="toggleBeneficiaryForm" type="button" class="text-xs font-bold text-slate-500 hover:text-slate-700">إلغاء</button>
                @endif
            </div>
            <div class="space-y-4">
                <div>
                    <label class="reg-label">الاسم الكامل <span class="reg-required">*</span></label>
                    <input wire:model.blur="beneficiaryName" type="text" class="reg-input">
                    @error('beneficiaryName') <p class="reg-field-error">{{ $message }}</p> @enderror
                </div>
                <div class="reg-grid-2">
                    <div>
                        <label class="reg-label">القرابة</label>
                        <select wire:model.live="beneficiaryRelationship" class="reg-select">
                            @foreach (\App\Enums\BeneficiaryRelationship::availableFor($maritalStatus) as $relationship)
                                <option value="{{ $relationship->value }}">{{ $relationship->label() }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-400">
                            @if ($maritalStatus === 'married')
                                متاح: الزوج/الزوجة، الأبناء، والأب والأم
                            @else
                                متاح حالياً: الأب والأم فقط
                            @endif
                        </p>
                    </div>
                    <div>
                        <label class="reg-label">فصيلة الدم</label>
                        <select wire:model.live="beneficiaryBloodType" class="reg-select">
                            @foreach (\App\Enums\BloodType::cases() as $blood)
                                <option value="{{ $blood->value }}">{{ $blood->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="reg-grid-2">
                    <div>
                        <label class="reg-label">الرقم الوطني <span class="reg-required">*</span></label>
                        <input wire:model.blur="beneficiaryNationalId" type="text" inputmode="numeric" maxlength="12" class="reg-input" placeholder="120020129499" dir="ltr">
                        <p class="mt-1 text-xs text-slate-400">12 رقماً — يبدأ بـ 1 للذكر أو 2 للأنثى</p>
                        @error('beneficiaryNationalId') <p class="reg-field-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="reg-label">تاريخ الميلاد <span class="reg-required">*</span></label>
                        <input wire:model.blur="beneficiaryDateOfBirth" type="date" class="reg-input">
                        @error('beneficiaryDateOfBirth') <p class="reg-field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="reg-label">صورة المستفيد <span class="reg-required">*</span></label>
                    <div class="reg-photo-picker">
                        <div @class([
                            'reg-photo-preview',
                            'reg-photo-preview-filled' => $beneficiaryPhoto || $beneficiaryExistingPhotoPath,
                        ])>
                            @if ($beneficiaryPhoto)
                                <img src="{{ $beneficiaryPhoto->temporaryUrl() }}" alt="معاينة صورة المستفيد" class="size-full object-cover">
                            @elseif ($beneficiaryExistingPhotoPath && $editingBeneficiaryIndex !== null)
                                <img src="{{ $this->beneficiaryPhotoUrl($beneficiaries[$editingBeneficiaryIndex] ?? null) }}" alt="صورة المستفيد" class="size-full object-cover">
                            @else
                                <div class="flex flex-col items-center gap-2 px-4 text-center">
                                    <svg class="size-9 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0 0 22.5 18.75V5.25A2.25 2.25 0 0 0 20.25 3H3.75A2.25 2.25 0 0 0 1.5 5.25v13.5A2.25 2.25 0 0 0 3.75 21Z"/></svg>
                                    <span class="text-xs text-slate-400">JPG أو PNG</span>
                                </div>
                            @endif

                            @if ($beneficiaryPhoto || $beneficiaryExistingPhotoPath)
                                <span class="reg-photo-badge">
                                    @if ($beneficiaryPhoto)
                                        معاينة جديدة
                                    @else
                                        محفوظة
                                    @endif
                                </span>
                            @endif
                        </div>

                        <label class="reg-btn-secondary mt-3 !min-h-11 w-full cursor-pointer sm:!w-auto sm:min-w-[10rem]">
                            <span wire:loading.remove wire:target="beneficiaryPhoto">
                                {{ ($beneficiaryPhoto || $beneficiaryExistingPhotoPath) ? 'تغيير الصورة' : 'اختيار صورة' }}
                            </span>
                            <span wire:loading wire:target="beneficiaryPhoto">جاري الرفع…</span>
                            <input wire:model="beneficiaryPhoto" type="file" accept="image/jpeg,image/png" class="sr-only">
                        </label>

                        @error('beneficiaryPhoto') <p class="reg-field-error mt-2">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h4 class="mb-3 text-sm font-extrabold text-navy-900">السجل الطبي للمستفيد</h4>
                    <div class="space-y-3">
                        <x-reg-medical-question
                            question="هل يعاني من أمراض مزمنة؟"
                            property="beneficiaryHasChronicConditions"
                            :value="$beneficiaryHasChronicConditions"
                        >
                            @if ($beneficiaryHasChronicConditions)
                                <div class="reg-medical-expand" wire:transition>
                                    <p class="reg-medical-expand-title">حدد الأمراض المزمنة:</p>
                                    <div class="reg-chronic-grid">
                                        @foreach ($chronicConditionOptions as $key => $label)
                                            <label class="reg-chronic-option" wire:key="beneficiary-chronic-{{ $key }}">
                                                <input
                                                    wire:model.live="beneficiaryChronicConditions"
                                                    type="checkbox"
                                                    value="{{ $key }}"
                                                    class="reg-chronic-checkbox"
                                                >
                                                <span class="reg-chronic-label">{{ $label }}</span>
                                                <span class="reg-chronic-check" aria-hidden="true">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @error('beneficiaryChronicConditions') <p class="reg-field-error mt-3">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </x-reg-medical-question>

                        @foreach ($beneficiaryMedicalQuestions as $q)
                            <x-reg-medical-question
                                :question="$q['ar']"
                                :property="$q['prop']"
                                :value="$this->{$q['prop']}"
                            />
                        @endforeach
                    </div>
                </div>

                <button
                    wire:click="saveBeneficiary"
                    type="button"
                    wire:loading.attr="disabled"
                    wire:target="saveBeneficiary,beneficiaryPhoto"
                    class="reg-btn-primary sm:!w-auto sm:min-w-[10rem]"
                >
                    <span wire:loading.remove wire:target="saveBeneficiary">
                        {{ $editingBeneficiaryIndex !== null ? 'تحديث' : 'حفظ المستفيد' }}
                    </span>
                    <span wire:loading wire:target="saveBeneficiary" class="inline-flex items-center gap-2">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        جاري الحفظ...
                    </span>
                </button>
            </div>
        </div>
    @endif

    @if (count($beneficiaries) > 0)
        @foreach ($beneficiaries as $index => $beneficiary)
            @continue($showBeneficiaryForm && $editingBeneficiaryIndex === $index)

            @php
                $rel = \App\Enums\BeneficiaryRelationship::from($beneficiary['relationship']);
                $blood = \App\Enums\BloodType::from($beneficiary['blood_type']);
            @endphp
            <article class="reg-beneficiary-card" wire:key="beneficiary-{{ $index }}">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <h4 class="text-lg font-extrabold text-navy-900">{{ $beneficiary['full_name'] }}</h4>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span class="reg-tag reg-tag-relation">{{ $rel->label() }}</span>
                            @if ($beneficiary['has_chronic_conditions'] ?? $beneficiary['has_chronic_condition'] ?? false)
                                <span class="reg-tag reg-tag-chronic">مرض مزمن</span>
                            @endif
                        </div>
                    </div>
                    @if (! empty($beneficiary['photo_path']) && $this->beneficiaryPhotoUrl($beneficiary))
                        <img src="{{ $this->beneficiaryPhotoUrl($beneficiary) }}" alt="" class="size-14 shrink-0 rounded-2xl object-cover">
                    @else
                        <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-2xl">{{ $rel->icon() }}</div>
                    @endif
                </div>
                <dl class="grid grid-cols-1 gap-2 text-sm text-slate-600 sm:grid-cols-3">
                    @if ($beneficiary['national_id'] ?? null)
                        <div><dt class="text-xs text-slate-400">الرقم الوطني</dt><dd class="font-bold text-slate-800">{{ $beneficiary['national_id'] }}</dd></div>
                    @endif
                    @if ($beneficiary['date_of_birth'] ?? null)
                        <div><dt class="text-xs text-slate-400">تاريخ الميلاد</dt><dd class="font-bold text-slate-800">{{ $beneficiary['date_of_birth'] }}</dd></div>
                    @endif
                    <div><dt class="text-xs text-slate-400">فصيلة الدم</dt><dd class="font-bold text-slate-800">{{ $blood->label() }}</dd></div>
                </dl>
                <div class="flex gap-2 border-t border-slate-100 pt-4">
                    <button wire:click="editBeneficiary({{ $index }})" type="button" wire:loading.attr="disabled" wire:target="editBeneficiary,deleteBeneficiary,saveBeneficiary" class="reg-btn-secondary flex-1 !min-h-[2.75rem] text-xs">تعديل</button>
                    <button wire:click="deleteBeneficiary({{ $index }})" wire:confirm="حذف هذا المستفيد؟" type="button" wire:loading.attr="disabled" wire:target="editBeneficiary,deleteBeneficiary,saveBeneficiary" class="reg-btn flex-1 !min-h-[2.75rem] border border-red-200 bg-red-50 text-xs font-bold text-red-600">حذف</button>
                </div>
            </article>
        @endforeach
    @elseif (! $showBeneficiaryForm)
        <div class="reg-card overflow-hidden border border-dashed border-slate-200 bg-gradient-to-b from-slate-50 to-white px-6 py-12 text-center">
            <div class="mx-auto mb-5 flex size-16 items-center justify-center rounded-3xl bg-teal-50 text-teal-600 shadow-sm ring-1 ring-teal-100">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
            </div>
            <h3 class="text-lg font-extrabold text-navy-900">لا يوجد مستفيدون بعد</h3>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-500">
                @if ($maritalStatus === 'married')
                    يمكنك إضافة الزوج/الزوجة والأبناء والوالدين، مع صورة وسجل طبي لكل مستفيد.
                @else
                    يمكنك إضافة الأب والأم، مع صورة وسجل طبي لكل مستفيد.
                @endif
            </p>
            <button wire:click="toggleBeneficiaryForm" type="button" class="reg-btn-primary mx-auto mt-6 sm:!w-auto sm:min-w-[12rem]">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                إضافة مستفيد
            </button>
        </div>
    @endif
</section>

@error('beneficiaries')
    <p class="reg-field-error px-1">{{ $message }}</p>
@enderror

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'continueFromBeneficiaries',
    'primaryLabel' => 'متابعة للمستندات',
    'primaryTarget' => 'continueFromBeneficiaries',
    'loadingLabel' => 'جاري المتابعة...',
])
