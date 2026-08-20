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
        dropdownStyle: '',
        previewLimit: 10,
        normalize(value) {
            return String(value ?? '')
                .toLocaleLowerCase('ar')
                .replace(/[أإآٱ]/g, 'ا')
                .replace(/ة/g, 'ه')
                .replace(/ى/g, 'ي')
                .replace(/ؤ/g, 'و')
                .replace(/ئ/g, 'ي')
                .replace(/[\u064B-\u065F]/g, '')
                .trim();
        },
        get entries() {
            const q = this.normalize(this.search);

            return Object.entries(this.options).filter(([key, label]) => {
                if (! q) {
                    return true;
                }

                return this.normalize(label).includes(q) || this.normalize(key).includes(q);
            });
        },
        get filtered() {
            if (this.normalize(this.search)) {
                return this.entries.slice(0, 40);
            }

            return this.entries.slice(0, this.previewLimit);
        },
        get remainingCount() {
            if (this.normalize(this.search)) {
                return Math.max(0, this.entries.length - this.filtered.length);
            }

            return Math.max(0, this.entries.length - this.previewLimit);
        },
        label() {
            return this.selected && this.options[this.selected] ? this.options[this.selected] : @js($placeholder);
        },
        choose(key) {
            this.selected = key;
            this.close();
        },
        close() {
            this.open = false;
            this.search = '';
            this.dropdownStyle = '';
        },
        toggle() {
            if (this.open) {
                this.close();

                return;
            }

            this.open = true;
            this.positionDropdown();
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        positionDropdown() {
            const trigger = this.$refs.trigger;

            if (! trigger || ! this.open) {
                return;
            }

            const rect = trigger.getBoundingClientRect();
            const gap = 4;
            const maxPanel = Math.min(240, Math.round(window.innerHeight * 0.42));
            const spaceBelow = window.innerHeight - rect.bottom - gap - 8;
            const spaceAbove = rect.top - gap - 8;
            const openUp = spaceBelow < 160 && spaceAbove > spaceBelow;
            const available = Math.max(140, openUp ? spaceAbove : spaceBelow);
            const height = Math.min(maxPanel, available);
            const width = Math.max(rect.width, 0);
            const left = Math.min(Math.max(8, rect.left), Math.max(8, window.innerWidth - width - 8));

            this.dropdownStyle = openUp
                ? `position:fixed;z-index:80;left:${left}px;width:${width}px;bottom:${window.innerHeight - rect.top + gap}px;max-height:${height}px;`
                : `position:fixed;z-index:80;left:${left}px;width:${width}px;top:${rect.bottom + gap}px;max-height:${height}px;`;
        },
        onKeydown(event) {
            if (event.key === 'Escape' && this.open) {
                this.close();
            }
        },
    }"
    x-on:click.outside="close()"
    x-on:keydown.window="onKeydown($event)"
    x-on:resize.window="open && positionDropdown()"
    x-on:scroll.window.capture="open && positionDropdown()"
    class="relative"
    {{ $attributes->whereDoesntStartWith('wire:model') }}
>
    <button
        type="button"
        x-ref="trigger"
        x-on:click="toggle()"
        class="reg-select flex w-full items-center justify-between gap-2 text-start"
    >
        <span x-text="label()" :class="selected ? 'text-slate-900' : 'text-slate-400'"></span>
        <svg class="size-4 shrink-0 text-slate-400 transition" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition.opacity.duration.150ms
        x-bind:style="dropdownStyle"
        class="flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
    >
        <div class="shrink-0 border-b border-slate-100 p-2">
            <input
                x-ref="searchInput"
                x-model="search"
                type="search"
                class="reg-input !min-h-10 text-sm"
                placeholder="{{ $searchPlaceholder }}"
                autocomplete="off"
            >
        </div>
        <ul class="min-h-0 flex-1 overflow-y-auto overscroll-contain py-1">
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
            <li x-show="remainingCount > 0" class="border-t border-slate-100 px-3 py-2.5 text-center text-xs font-medium text-slate-400">
                <span x-text="normalize(search) ? ('+' + remainingCount + ' نتيجة أخرى') : ('اكتب للبحث عن ' + remainingCount + ' خياراً إضافياً')"></span>
            </li>
        </ul>
    </div>
</div>
