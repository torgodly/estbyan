@props([
    'options' => [],
    'placeholder' => '— اختر —',
    'searchPlaceholder' => 'ابحث...',
])

@php
    $wireModel = $attributes->wire('model');
    $model = $wireModel->value();
    $live = $wireModel->hasModifier('live') ? 'true' : 'false';
@endphp

<div
    x-data="{
        open: false,
        search: '',
        selected: $wire.$entangle('{{ $model }}', {{ $live }}),
        options: {{ \Illuminate\Support\Js::from($options) }},
        get filtered() {
            const q = this.search.trim();
            return Object.entries(this.options).filter(([, label]) => ! q || String(label).includes(q));
        },
        label() {
            return this.selected && this.options[this.selected] ? this.options[this.selected] : @js($placeholder);
        },
        choose(key) {
            this.selected = key;
            this.open = false;
            this.search = '';
        }
    }"
    x-on:click.outside="open = false"
    class="relative"
    {{ $attributes->whereDoesntStartWith('wire:model') }}
>
    <button
        type="button"
        x-on:click="open = ! open; if (open) $nextTick(() => $refs.searchInput?.focus())"
        class="reg-select flex w-full items-center justify-between gap-2 text-start"
    >
        <span x-text="label()" :class="selected ? 'text-slate-900' : 'text-slate-400'"></span>
        <svg class="size-4 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        class="absolute inset-x-0 z-40 mt-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
    >
        <div class="border-b border-slate-100 p-2">
            <input
                x-ref="searchInput"
                x-model="search"
                type="search"
                class="reg-input !min-h-10 text-sm"
                placeholder="{{ $searchPlaceholder }}"
                autocomplete="off"
            >
        </div>
        <ul class="max-h-56 overflow-y-auto py-1">
            <template x-for="[key, label] in filtered" :key="key">
                <li>
                    <button
                        type="button"
                        x-on:click="choose(key)"
                        class="flex w-full items-center px-3 py-2.5 text-start text-sm font-medium text-slate-700 hover:bg-teal-50 hover:text-teal-800"
                        :class="selected === key && 'bg-teal-50 font-bold text-teal-800'"
                        x-text="label"
                    ></button>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3 py-3 text-center text-xs text-slate-400">لا توجد نتائج</li>
        </ul>
    </div>
</div>
