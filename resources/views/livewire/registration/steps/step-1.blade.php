@if ($approvedLocked)
    <section class="reg-login-shell">
        @include('livewire.registration.partials.login-brand')

        <div class="reg-login-card text-center">
            <div class="mx-auto mb-5 flex size-16 items-center justify-center rounded-full bg-teal-50 text-teal-700">
                <svg class="size-8" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>
            <h1 class="text-2xl font-extrabold text-navy-900">الطلب معتمد</h1>
            <p class="mx-auto mt-3 max-w-md text-sm leading-relaxed text-slate-500">{{ $approvedMessage }}</p>
            @if ($referenceNumber)
                <div class="mt-6 flex justify-center">
                    @include('livewire.registration.partials.reference-summary', [
                        'referenceNumber' => $referenceNumber,
                        'fullName' => $verifiedFullName ?: $fullName,
                        'nationalId' => $nationalId,
                        'employeeNumber' => $employeeNumber,
                    ])
                </div>
                <a href="{{ route('registration.reference-card', $registrationId) }}" class="reg-btn-primary mt-6">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    تحميل بطاقة المراجعة
                </a>
            @endif
        </div>

        @include('livewire.registration.partials.login-contact')
    </section>
@else
    <section class="reg-login-shell">
        @include('livewire.registration.partials.login-brand')

        <div class="reg-login-card">
            <div class="reg-login-heading">
                <p class="reg-login-kicker">مصلحة الضرائب الليبية · الرعاية الذكية</p>
                <h1 class="reg-login-title">منظومة الاستبيان</h1>
                <p class="reg-login-subtitle">منظومة إدخال بيانات الموظفين وعوائلهم</p>
            </div>

            <div class="mb-6 rounded-2xl border border-slate-100 bg-slate-50/80 px-4 py-3 text-center">
                <p class="text-sm font-bold text-navy-900">تسجيل الدخول للموظفين</p>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">أدخل الرقم الوطني للتحقق من هويتك</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="reg-label" for="natId">الرقم الوطني <span class="reg-required">*</span></label>
                    <div class="reg-login-field">
                        <span class="reg-login-field-icon" aria-hidden="true">
                            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Zm6-10.125a1.875 1.875 0 1 1-3.75 0 1.875 1.875 0 0 1 3.75 0Zm1.294 6.336a6.721 6.721 0 0 1-3.17.789 6.721 6.721 0 0 1-3.168-.789 3.376 3.376 0 0 1 6.338 0Z"/></svg>
                        </span>
                        <input
                            wire:model.blur="nationalId"
                            id="natId"
                            type="text"
                            inputmode="numeric"
                            autocomplete="username"
                            class="reg-input reg-login-input"
                            placeholder="119990000000"
                            maxlength="12"
                            dir="ltr"
                        >
                    </div>
                    @error('nationalId') <p class="reg-field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="reg-consent mt-6">
                <input wire:model.live="consent" type="checkbox" class="mt-1 size-5 shrink-0 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                <span class="text-sm leading-relaxed text-slate-600">
                    أوافق على جمع ومعالجة بياناتي الشخصية والطبية لإدارة التغطية الصحية.
                    <span class="mt-1 block text-xs text-slate-400" dir="ltr">I consent to Smart Care processing my personal and medical data.</span>
                </span>
            </label>
            @error('consent') <p class="reg-field-error mt-2">{{ $message }}</p> @enderror

            <button
                type="button"
                wire:click="verifyIdentity"
                wire:loading.attr="disabled"
                wire:target="verifyIdentity"
                class="reg-btn-primary mt-6"
                @disabled(! $consent)
            >
                <span wire:loading.remove wire:target="verifyIdentity" class="inline-flex items-center gap-2">
                    دخول ومتابعة
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                </span>
                <span wire:loading wire:target="verifyIdentity" class="inline-flex items-center gap-2">
                    <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    جاري التحقق...
                </span>
            </button>
        </div>

        @include('livewire.registration.partials.login-contact')
    </section>
@endif
