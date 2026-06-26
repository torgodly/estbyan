<section class="reg-card">
    <div class="reg-card-header">
        <h2 class="reg-card-title">بيانات الهوية</h2>
        <p class="reg-card-subtitle">أدخل بياناتك كما هي في سجلك الرسمي — تُحفظ تلقائياً ويمكنك المتابعة لاحقاً.</p>
    </div>

    <div class="space-y-5">
        <div>
            <label class="reg-label" for="fullName">الاسم الكامل <span class="reg-required">*</span></label>
            <input wire:model.blur="fullName" id="fullName" type="text" autocomplete="name" class="reg-input" placeholder="الاسم كما في السجل الرسمي">
            @error('fullName') <p class="reg-field-error">{{ $message }}</p> @enderror
        </div>

        <div class="reg-grid-2">
            <div>
                <label class="reg-label" for="empNo">الرقم الوظيفي <span class="reg-required">*</span></label>
                <input wire:model.blur="employeeNumber" id="empNo" type="text" inputmode="numeric" autocomplete="off" class="reg-input" placeholder="مثال: 1001">
                @error('employeeNumber') <p class="reg-field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="reg-label" for="natId">الرقم الوطني <span class="reg-required">*</span></label>
                <input wire:model.blur="nationalId" id="natId" type="text" inputmode="numeric" class="reg-input" placeholder="XXXXXXXXXXXX">
                @error('nationalId') <p class="reg-field-error">{{ $message }}</p> @enderror
            </div>
        </div>
        <div class="sm:max-w-xs">
            <label class="reg-label" for="dob">تاريخ الميلاد <span class="reg-required">*</span></label>
            <input wire:model.blur="dateOfBirth" id="dob" type="date" class="reg-input">
            @error('dateOfBirth') <p class="reg-field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <label class="reg-consent mt-6">
        <input wire:model.live="consent" type="checkbox" class="mt-1 size-5 shrink-0 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
        <span class="text-sm leading-relaxed text-slate-600">
            أوافق صراحةً على قيام شركة الرعاية الذكية بجمع ومعالجة بياناتي الشخصية والطبية لغرض إدارة التغطية الصحية ومعالجة المطالبات، وفقاً للقوانين المعمول بها.
            <span class="mt-2 block text-xs text-slate-400" dir="ltr">I consent to Smart Care collecting and processing my personal and medical data.</span>
        </span>
    </label>
    @error('consent') <p class="reg-field-error mt-2">{{ $message }}</p> @enderror
</section>

@include('livewire.registration.partials.actions', [
    'primaryAction' => 'verifyIdentity',
    'primaryLabel' => 'متابعة',
    'primaryLoading' => true,
    'primaryTarget' => 'verifyIdentity',
    'primaryDisabled' => ! $consent,
    'showBack' => false,
])
