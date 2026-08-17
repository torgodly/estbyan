<section class="reg-card">
    <div class="reg-identity-card mb-6">
        <p class="text-center text-[11px] font-bold tracking-wide text-teal-300/90">بيانات الهوية</p>
        <p class="mt-2 text-center text-lg font-extrabold leading-snug sm:text-xl">{{ $verifiedFullName }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-2.5 text-center sm:gap-3">
            <div class="rounded-xl bg-white/10 px-2.5 py-3">
                <dt class="text-[11px] font-medium text-slate-300">الرقم الوظيفي</dt>
                <dd class="mt-1 text-sm font-bold tracking-wide sm:text-base" dir="ltr">{{ $employeeNumber }}</dd>
            </div>
            <div class="rounded-xl bg-white/10 px-2.5 py-3">
                <dt class="text-[11px] font-medium text-slate-300">الرقم الوطني</dt>
                <dd class="mt-1 truncate text-sm font-bold tracking-wide sm:text-base" dir="ltr">{{ $nationalId }}</dd>
            </div>
        </dl>
    </div>

    <div class="reg-card-header !mb-5 !pb-0 !border-0">
        <h2 class="reg-card-title">بيانات الموظف</h2>
        <p class="reg-card-subtitle">أكمل معلوماتك الوظيفية والشخصية</p>
    </div>

    <div class="space-y-5">
        <div class="reg-grid-2">
            <div>
                <label class="reg-label">مكان العمل <span class="reg-required">*</span></label>
                @if ($identityLocked)
                    <div class="reg-input bg-slate-50 font-bold text-navy-900">
                        {{ $workplaces[$workplace] ?? $workplace }}
                    </div>
                @else
                    <select wire:model.live="workplace" class="reg-select">
                        <option value="">— اختر —</option>
                        @foreach ($workplaces as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                @error('workplace') <p class="reg-field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="reg-label">المسمى الوظيفي</label>
                <select wire:model.live="jobTitle" class="reg-select">
                    @foreach ($jobTitles as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="reg-grid-2">
            <div>
                <label class="reg-label">تاريخ الميلاد <span class="reg-required">*</span></label>
                <input wire:model.blur="dateOfBirth" type="date" class="reg-input">
                @if (\App\Support\LibyanNationalId::isValid($nationalId))
                    <p class="mt-1 text-xs text-slate-400">يجب أن تكون سنة الميلاد {{ \App\Support\LibyanNationalId::birthYear($nationalId) }} حسب الرقم الوطني</p>
                @endif
                @error('dateOfBirth') <p class="reg-field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="reg-label">الجنس <span class="reg-required">*</span></label>
                <div class="reg-input bg-slate-50 font-bold text-navy-900">
                    {{ $gender === 'female' ? 'أنثى' : 'ذكر' }}
                </div>
                <p class="mt-1 text-xs text-slate-400">يُستخرج تلقائياً من الرقم الوطني ولا يمكن تعديله</p>
            </div>
        </div>

        <div class="reg-grid-2">
            <div>
                <label class="reg-label">الحالة الاجتماعية <span class="reg-required">*</span></label>
                <select wire:model.live="maritalStatus" class="reg-select">
                    <option value="single">أعزب / عزباء</option>
                    <option value="married">متزوج / متزوجة</option>
                </select>
            </div>
            <div>
                <label class="reg-label">عدد المستفيدين <span class="reg-required">*</span></label>
                <input wire:model.blur="beneficiariesCount" type="number" inputmode="numeric" min="0" max="20" class="reg-input" placeholder="0">
                <p class="mt-1 text-xs text-slate-400">
                    @if ($maritalStatus === 'married')
                        يمكن إضافة الزوج/الزوجة والأبناء والوالدين
                    @else
                        يمكن إضافة الوالدين
                    @endif
                </p>
                @error('beneficiariesCount') <p class="reg-field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="mt-8">
        <h3 class="reg-section-title">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
            معلومات التواصل
        </h3>
        <div class="space-y-4">
            <div class="reg-grid-2">
                <div>
                    <label class="reg-label">رقم الهاتف <span class="reg-required">*</span></label>
                    <input wire:model.blur="phone" type="tel" inputmode="tel" class="reg-input" placeholder="09XXXXXXXX">
                    @error('phone') <p class="reg-field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="reg-label">واتساب</label>
                    <input wire:model.blur="whatsapp" type="tel" inputmode="tel" class="reg-input">
                </div>
            </div>
            <div>
                <label class="reg-label">البريد الإلكتروني</label>
                <input wire:model.blur="email" type="email" dir="ltr" class="reg-input text-left">
            </div>
            <div class="reg-grid-2">
                <div>
                    <label class="reg-label">المدينة <span class="reg-required">*</span></label>
                    <x-reg-searchable-select
                        wire:model.live="city"
                        :options="$cities"
                        placeholder="— اختر المدينة —"
                        search-placeholder="ابحث عن المدينة..."
                    />
                    @error('city') <p class="reg-field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="reg-label">العنوان السكني <span class="reg-required">*</span></label>
                    <input wire:model.blur="address" type="text" class="reg-input">
                    @error('address') <p class="reg-field-error">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>
    </div>
</section>

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'saveEmployeeDetails',
    'primaryLabel' => 'حفظ ومتابعة',
    'primaryTarget' => 'saveEmployeeDetails',
    'loadingLabel' => 'جاري الحفظ...',
])
