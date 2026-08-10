@if ($toastMessage)
    <div
        wire:key="toast-{{ md5($toastMessage) }}"
        class="reg-toast-host"
        x-data="{
            show: false,
            dismiss() {
                if (! this.show) {
                    return;
                }

                this.show = false;
                setTimeout(() => $wire.dismissToast(), 280);
            },
        }"
        x-init="
            requestAnimationFrame(() => { show = true });
            setTimeout(() => dismiss(), 4200);
        "
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-3 scale-[0.97]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-250"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 -translate-y-2 scale-[0.97]"
        style="display: none;"
    >
        <div class="reg-toast" role="status" aria-live="polite">
            <div class="reg-toast-icon" aria-hidden="true">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            </div>

            <p class="reg-toast-message">{{ $toastMessage }}</p>

            <button
                type="button"
                class="reg-toast-close"
                @click="dismiss()"
                aria-label="إغلاق"
            >
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>

            <div class="reg-toast-progress" dir="ltr" aria-hidden="true"></div>
        </div>
    </div>
@endif

@if ($hasSavedDraft && ! $submitted && ! $approvedLocked)
    <div class="reg-alert-info">
        <svg class="size-5 shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
        <span>تم استعادة بياناتك المحفوظة — يمكنك المتابعة من حيث توقفت</span>
    </div>
@endif
