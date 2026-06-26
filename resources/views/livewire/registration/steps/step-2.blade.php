<section class="reg-card">
    <div class="reg-identity-card mb-6">
        <p class="text-center text-xs font-bold uppercase tracking-widest text-teal-300/80">بيانات الهوية</p>
        <p class="mt-1 text-center text-xl font-extrabold">{{ $verifiedFullName }}</p>
        <dl class="mt-4 grid grid-cols-3 gap-3 text-center text-sm">
            <div class="rounded-xl bg-white/10 px-2 py-2.5">
                <dt class="text-[10px] uppercase text-slate-300">وظيفي</dt>
                <dd class="mt-0.5 font-bold">{{ $employeeNumber }}</dd>
            </div>
            <div class="rounded-xl bg-white/10 px-2 py-2.5">
                <dt class="text-[10px] uppercase text-slate-300">وطني</dt>
                <dd class="mt-0.5 truncate font-bold">{{ $nationalId }}</dd>
            </div>
            <div class="rounded-xl bg-white/10 px-2 py-2.5">
                <dt class="text-[10px] uppercase text-slate-300">الميلاد</dt>
                <dd class="mt-0.5 font-bold">{{ $dateOfBirth }}</dd>
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
                <select wire:model.live="workplace" class="reg-select">
                    <option value="">— اختر —</option>
                    @foreach ($workplaces as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
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
                <label class="reg-label">الجنس <span class="reg-required">*</span></label>
                <select wire:model.live="gender" class="reg-select">
                    <option value="male">ذكر</option>
                    <option value="female">أنثى</option>
                </select>
            </div>
            <div>
                <label class="reg-label">الحالة الاجتماعية <span class="reg-required">*</span></label>
                <select wire:model.live="maritalStatus" class="reg-select">
                    <option value="single">أعزب / عزباء</option>
                    <option value="married">متزوج / متزوجة</option>
                </select>
            </div>
        </div>

        <div class="sm:max-w-xs">
            <label class="reg-label">عدد المستفيدين <span class="reg-required">*</span></label>
            <input wire:model.blur="beneficiariesCount" type="number" inputmode="numeric" min="0" max="20" class="reg-input" placeholder="0">
            @error('beneficiariesCount') <p class="reg-field-error">{{ $message }}</p> @enderror
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
                    <select wire:model.live="city" class="reg-select">
                        <option value="">— اختر —</option>
                        @foreach ($cities as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
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
    'primaryLoading' => true,
    'primaryTarget' => 'saveEmployeeDetails',
])
