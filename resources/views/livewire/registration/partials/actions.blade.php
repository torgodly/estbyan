@props([
    'primaryAction',
    'primaryLabel',
    'primaryTarget' => null,
    'primaryDisabled' => false,
    'showBack' => true,
    'loadingLabel' => 'جاري المعالجة...',
])

@php
    $target = $primaryTarget ?: $primaryAction;
    $loadingTargets = $showBack ? "{$target},goBack" : $target;
@endphp

<div class="reg-actions">
    <div class="reg-actions-dock">
        @include('livewire.registration.partials.validation-summary', ['compact' => true])

        <div class="reg-actions-inner">
            <button
                type="button"
                wire:click="{{ $primaryAction }}"
                wire:loading.attr="disabled"
                wire:target="{{ $loadingTargets }}"
                class="reg-btn-primary"
                @disabled($primaryDisabled)
            >
                <span wire:loading.remove wire:target="{{ $target }}" class="inline-flex items-center gap-2">
                    {{ $primaryLabel }}
                </span>
                <span wire:loading wire:target="{{ $target }}" class="inline-flex items-center gap-2">
                    <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    {{ $loadingLabel }}
                </span>
            </button>

            @if ($showBack)
                <button
                    type="button"
                    wire:click="goBack"
                    wire:loading.attr="disabled"
                    wire:target="{{ $loadingTargets }}"
                    class="reg-btn-secondary"
                >
                    <span wire:loading.remove wire:target="goBack">رجوع</span>
                    <span wire:loading wire:target="goBack" class="inline-flex items-center gap-2">
                        <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        جاري الرجوع...
                    </span>
                </button>
            @endif

            {{ $slot ?? '' }}
        </div>
    </div>
</div>
