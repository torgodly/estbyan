@props(['question', 'property', 'value'])

<div
    @class([
        'reg-medical-block',
        'reg-medical-block-yes' => $value === true,
        'reg-medical-block-no' => $value === false,
    ])
    wire:key="medical-block-{{ $property }}"
>
    <div class="reg-medical-block-head">
        <p class="reg-medical-question">{{ $question }}</p>

        <div class="reg-segment" role="group" aria-label="{{ $question }}">
            <button
                type="button"
                wire:click="$set('{{ $property }}', true)"
                @class(['reg-segment-btn', 'reg-segment-btn-yes' => $value === true])
                aria-pressed="{{ $value === true ? 'true' : 'false' }}"
            >
                نعم
            </button>
            <button
                type="button"
                wire:click="$set('{{ $property }}', false)"
                @class(['reg-segment-btn', 'reg-segment-btn-no' => $value === false])
                aria-pressed="{{ $value === false ? 'true' : 'false' }}"
            >
                لا
            </button>
        </div>
    </div>

    {{ $slot }}
</div>
