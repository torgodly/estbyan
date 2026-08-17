<section class="reg-card flex flex-col items-center py-10 text-center lg:py-16">
    <div class="reg-success-icon">
        <svg class="size-14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
        </svg>
    </div>
    <h2 class="text-2xl font-extrabold text-navy-900 sm:text-3xl">تم إرسال التسجيل بنجاح</h2>
    <p class="mt-3 max-w-md text-sm leading-relaxed text-slate-500">شكراً لك. تم استلام طلبك وسيتم مراجعته من قبل فريق الرعاية الذكية. احتفظ برقم المرجع للمتابعة.</p>
    <div class="mt-8 flex w-full justify-center">
        @include('livewire.registration.partials.reference-summary', [
            'referenceNumber' => $referenceNumber,
            'fullName' => $verifiedFullName ?: $fullName,
            'nationalId' => $nationalId,
            'employeeNumber' => $employeeNumber,
        ])
    </div>

    <div class="mt-8 flex w-full max-w-md flex-col gap-3 sm:flex-row sm:justify-center">
        <a
            href="{{ route('registration.reference-card', $registrationId) }}"
            class="reg-btn-primary inline-flex items-center justify-center gap-2 sm:!w-auto sm:min-w-[14rem]"
        >
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            تحميل بطاقة المراجعة
        </a>
        <button
            type="button"
            wire:click="editSubmittedRegistration"
            wire:loading.attr="disabled"
            wire:target="editSubmittedRegistration"
            class="reg-btn-secondary inline-flex items-center justify-center gap-2 sm:!w-auto sm:min-w-[12rem]"
        >
            <span wire:loading.remove wire:target="editSubmittedRegistration" class="inline-flex items-center gap-2">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/></svg>
                تعديل الطلب
            </span>
            <span wire:loading wire:target="editSubmittedRegistration" class="inline-flex items-center gap-2">
                <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                جاري فتح التعديل...
            </span>
        </button>
    </div>
</section>
