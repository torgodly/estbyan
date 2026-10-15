@php
    $family = $family ?? [];
    $members = $family['members'] ?? [];
    $statePath = $getStatePath();
    $id = $getId();
    $wireModelAttribute = $applyStateBindingModifiers('wire:model');
    $state = $getState();
    $stateValue = $state instanceof \BackedEnum ? $state->value : (string) ($state ?? '');
    $alreadyDelivered = (bool) ($family['is_delivered'] ?? false);
@endphp

<div class="hr-review hr-deliver-modal" dir="rtl">
    <div class="hr-deliver-modal__summary">
        <div class="hr-deliver-modal__summary-head">
            <div>
                <p class="hr-deliver-modal__label">
                    {{ $alreadyDelivered ? 'عائلة مُسلّمة سابقاً' : 'عائلة جاهزة للتسليم' }}
                </p>
                <h3 class="hr-deliver-modal__name">{{ $family['employee_name'] ?? '—' }}</h3>
                <p class="hr-deliver-modal__meta">
                    {{ $family['scanned_count'] ?? 0 }} بطاقات
                    @if (filled($family['reference'] ?? null))
                        · {{ $family['reference'] }}
                    @endif
                </p>
            </div>
            <span @class(['hr-chip', 'hr-chip--submitted' => $alreadyDelivered, 'hr-chip--approved' => ! $alreadyDelivered])>
                {{ $alreadyDelivered ? 'مُسلّمة' : 'مكتملة' }}
            </span>
        </div>

        @if ($alreadyDelivered)
            <div class="hr-deliver-modal__prior">
                سُلّمت سابقاً إلى {{ $family['delivered_to_label'] ?? '—' }}
                @if (filled($family['delivered_at'] ?? null))
                    · {{ $family['delivered_at'] }}
                @endif
                @if (filled($family['delivered_by_name'] ?? null))
                    · بواسطة {{ $family['delivered_by_name'] }}
                @endif
            </div>
        @endif

        @if ($members !== [])
            <ul class="hr-deliver-modal__members">
                @foreach ($members as $member)
                    @continue(! ($member['scanned'] ?? false))
                    <li>
                        <strong>{{ $member['name'] }}</strong>
                        <span>{{ $member['role_label'] }}@if (filled($member['card_label']) && $member['card_label'] !== '—') · {{ $member['card_label'] }} @endif</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div>
        <p class="hr-deliver-modal__ask">
            إلى من سُلّمت البطاقات؟
        </p>
        <p class="hr-deliver-modal__hint">
            اختر جهة التسليم قبل التأكيد. لا يمكن التراجع بعد التسجيل.
        </p>
    </div>

    <div class="hr-deliver-modal__choices" role="radiogroup" aria-label="جهة التسليم">
        @foreach (\App\Enums\CardDeliveryRecipient::cases() as $option)
            <label class="hr-deliver-choice">
                <input
                    type="radio"
                    class="hr-deliver-choice__input"
                    name="{{ $id }}"
                    value="{{ $option->value }}"
                    @checked($stateValue === $option->value)
                    {{ $wireModelAttribute }}="{{ $statePath }}"
                >
                <span class="hr-deliver-choice__icon" aria-hidden="true">
                    <x-filament::icon :icon="$option->icon()" class="h-5 w-5" />
                </span>
                <span class="hr-deliver-choice__copy">
                    <strong>{{ $option->getLabel() }}</strong>
                    <span>{{ $option->description() }}</span>
                </span>
            </label>
        @endforeach
    </div>
</div>
