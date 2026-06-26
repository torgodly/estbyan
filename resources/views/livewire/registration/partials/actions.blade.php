@props(['primaryAction', 'primaryLabel', 'primaryLoading' => false, 'primaryTarget' => null, 'primaryDisabled' => false, 'showBack' => true])

<div class="reg-actions">
    <div class="reg-actions-inner">
        <button
            wire:click="{{ $primaryAction }}"
            wire:loading.attr="disabled"
            @if($primaryTarget) wire:target="{{ $primaryTarget }}" @endif
            class="reg-btn-primary"
            @disabled($primaryDisabled)
        >
            @if ($primaryLoading && $primaryTarget)
                <span wire:loading.remove wire:target="{{ $primaryTarget }}">{{ $primaryLabel }}</span>
                <span wire:loading wire:target="{{ $primaryTarget }}" class="flex items-center gap-2">
                    <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    جاري المعالجة...
                </span>
            @else
                {{ $primaryLabel }}
            @endif
        </button>

        @if ($showBack)
            <button wire:click="goBack" type="button" class="reg-btn-secondary">رجوع</button>
        @endif

        {{ $slot ?? '' }}
    </div>
</div>
