<div class="reg-page">
    <div class="reg-container">
        @include('livewire.registration.partials.sidebar')

        <div class="reg-main">
            {{-- Mobile clear button --}}
            @if (! $submitted)
                <div class="flex justify-end px-4 pt-3 lg:hidden" style="padding-top: max(0.75rem, env(safe-area-inset-top))">
                    <button
                        type="button"
                        wire:click="clearForm"
                        wire:confirm="هل تريد مسح جميع البيانات والبدء من جديد؟"
                        wire:loading.attr="disabled"
                        class="flex items-center gap-1.5 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-bold text-red-600"
                    >
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                        مسح الكل
                    </button>
                </div>
            @endif

            <div class="reg-main-inner">
                @include('livewire.registration.partials.alerts')

                @if ($submitted)
                    @include('livewire.registration.steps.success')
                @else
                    @include(match ($step) {
                        1 => 'livewire.registration.steps.step-1',
                        2 => 'livewire.registration.steps.step-2',
                        3 => 'livewire.registration.steps.step-3',
                        4 => 'livewire.registration.steps.step-4',
                        5 => 'livewire.registration.steps.step-5',
                        6 => 'livewire.registration.steps.step-6',
                        default => 'livewire.registration.steps.step-1',
                    })
                @endif
            </div>

            <footer class="hidden px-10 pb-6 text-center text-xs text-slate-400 lg:block">
                <p>Smart Care Inc. · التسجيل الطبي للموظفين © {{ date('Y') }}</p>
            </footer>
        </div>
    </div>
</div>
