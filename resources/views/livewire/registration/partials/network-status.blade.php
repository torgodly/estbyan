{{-- Thin top bar for any Livewire request (SPA / slow network feedback) --}}
<div
    wire:loading.delay.shortest
    class="reg-network-progress"
    role="status"
    aria-live="polite"
    aria-label="جاري التحميل"
>
    <div class="reg-network-progress-bar"></div>
</div>

{{-- Soft overlay only for heavy navigational actions --}}
<div
    wire:loading.flex
    wire:target="verifyIdentity,saveEmployeeDetails,saveMedicalRecord,continueFromBeneficiaries,saveDocuments,submitRegistration,saveDraft,saveBeneficiary,editSubmittedRegistration,clearForm,goBack,logout"
    class="reg-loading-overlay"
    role="status"
    aria-live="assertive"
>
    <div class="reg-loading-card">
        <svg class="size-8 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
        <p class="mt-3 text-sm font-extrabold text-navy-900">يرجى الانتظار…</p>
        <p class="mt-1 text-xs text-slate-500">الاتصال بطيء؟ لا تغلق الصفحة.</p>
    </div>
</div>

{{-- Offline banner --}}
<div wire:offline class="reg-offline-banner" role="alert">
    <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z"/>
    </svg>
    <span>لا يوجد اتصال بالإنترنت. أعد المحاولة عند عودة الشبكة.</span>
</div>
