@if ($toastMessage)
    <div class="reg-alert-toast" wire:key="toast-{{ md5($toastMessage) }}">
        <svg class="size-5 shrink-0 text-teal-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
        <span class="flex-1 leading-relaxed">{{ $toastMessage }}</span>
        <button type="button" wire:click="dismissToast" class="rounded-lg bg-white/10 px-2.5 py-1 text-xs font-bold hover:bg-white/20">إغلاق</button>
    </div>
@endif

@if ($hasSavedDraft && ! $submitted)
    <div class="reg-alert-info">
        <svg class="size-5 shrink-0 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/></svg>
        <span>تم استعادة بياناتك المحفوظة — يمكنك المتابعة من حيث توقفت</span>
    </div>
@endif
