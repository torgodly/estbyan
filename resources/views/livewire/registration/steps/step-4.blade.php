<section class="space-y-5">
    <div class="reg-card">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="reg-card-title">المستفيدون</h2>
                <p class="reg-card-subtitle !mt-1">أفراد العائلة المشمولين بالتغطية التأمينية</p>
            </div>
            <button wire:click="toggleBeneficiaryForm" type="button" @class(['shrink-0 sm:!w-auto sm:min-w-[10rem]', $showBeneficiaryForm ? 'reg-btn-secondary' : 'reg-btn-primary'])>
                @if ($showBeneficiaryForm)
                    إلغاء
                @else
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    إضافة مستفيد
                @endif
            </button>
        </div>
    </div>

    @if ($showBeneficiaryForm)
        <div class="reg-card border-2 border-dashed border-teal-200 bg-teal-50/20">
            <h3 class="mb-4 text-sm font-extrabold text-teal-800">{{ $editingBeneficiaryIndex !== null ? 'تعديل مستفيد' : 'مستفيد جديد' }}</h3>
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
                            <option value="spouse">زوج / زوجة</option>
                            <option value="son">ابن</option>
                            <option value="daughter">ابنة</option>
                        </select>
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
                        <label class="reg-label">الرقم الوطني</label>
                        <input wire:model.blur="beneficiaryNationalId" type="text" inputmode="numeric" class="reg-input">
                    </div>
                    <div>
                        <label class="reg-label">تاريخ الميلاد</label>
                        <input wire:model.blur="beneficiaryDateOfBirth" type="date" class="reg-input">
                    </div>
                </div>
                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                    <input wire:model.live="beneficiaryHasChronic" type="checkbox" class="size-5 rounded text-teal-600 focus:ring-teal-500">
                    <span class="text-sm font-medium">لديه مرض مزمن</span>
                </label>
                <button wire:click="saveBeneficiary" type="button" class="reg-btn-primary sm:!w-auto sm:min-w-[10rem]">
                    {{ $editingBeneficiaryIndex !== null ? 'تحديث' : 'حفظ المستفيد' }}
                </button>
            </div>
        </div>
    @endif

    @forelse ($beneficiaries as $index => $beneficiary)
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
                        @if ($beneficiary['has_chronic_condition'] ?? false)
                            <span class="reg-tag reg-tag-chronic">مرض مزمن</span>
                        @endif
                    </div>
                </div>
                <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-teal-50 text-2xl">{{ $rel->icon() }}</div>
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
                <button wire:click="editBeneficiary({{ $index }})" type="button" class="reg-btn-secondary flex-1 !min-h-[2.75rem] text-xs">تعديل</button>
                <button wire:click="deleteBeneficiary({{ $index }})" wire:confirm="حذف هذا المستفيد؟" type="button" class="reg-btn flex-1 !min-h-[2.75rem] border border-red-200 bg-red-50 text-xs font-bold text-red-600">حذف</button>
            </div>
        </article>
    @empty
        <div class="reg-empty">
            <svg class="mb-3 size-10 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
            <p class="font-bold text-slate-500">لا يوجد مستفيدون بعد</p>
            <p class="mt-1 text-xs text-slate-400">اضغط «إضافة مستفيد» لإضافة أفراد العائلة</p>
        </div>
    @endforelse
</section>

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'continueFromBeneficiaries',
    'primaryLabel' => 'متابعة للمستندات',
])
