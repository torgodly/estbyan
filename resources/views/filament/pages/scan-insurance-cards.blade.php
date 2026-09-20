@php
    $groups = $this->groups();
    $scannedCount = $this->scannedCount();
    $familyCount = $this->familyCount();
@endphp

<x-filament-panels::page>
    <div
        dir="rtl"
        class="hr-review hr-scan"
        x-data="{
            storageKey: 'estbyan.scan-insurance-cards',
            ready: false,
            async init() {
                const saved = this.read();

                if (saved.length > 0 && this.$wire.scanned.length === 0) {
                    await this.$wire.restoreScanned(saved);
                }

                this.ready = true;
                this.persist(this.$wire.scanned);
            },
            read() {
                try {
                    const parsed = JSON.parse(localStorage.getItem(this.storageKey) || '[]');

                    return Array.isArray(parsed) ? parsed : [];
                } catch (error) {
                    return [];
                }
            },
            persist(items) {
                if (! this.ready) {
                    return;
                }

                const list = Array.isArray(items) ? items : [];

                if (list.length === 0) {
                    localStorage.removeItem(this.storageKey);

                    return;
                }

                localStorage.setItem(this.storageKey, JSON.stringify(list));
            },
            clearCache() {
                localStorage.removeItem(this.storageKey);
                this.$wire.clearScanned();
            },
        }"
        x-init="$watch('$wire.scanned', (items) => persist(items))"
        x-on:insurance-card-scanned.window="$nextTick(() => { $refs.scan?.focus(); $refs.scan?.select(); })"
    >
        <section class="hr-panel">
            <div class="hr-panel__head">
                <div>
                    <h3 class="hr-panel__title">مسح البطاقة</h3>
                    <p class="hr-panel__meta">امسح الباركود أو اكتب الرقم ثم اضغط إدخال. آخر عائلة تُمسح تظهر أولاً. القائمة تبقى محفوظة في هذا المتصفح بعد تحديث الصفحة.</p>
                </div>
                <div class="hr-scan__counts">
                    <span class="hr-chip">{{ $scannedCount }} بطاقة</span>
                    <span class="hr-chip">{{ $familyCount }} عائلة</span>
                </div>
            </div>
            <div class="hr-panel__body hr-scan__bar-body">
                <form class="hr-scan__bar" wire:submit="scanCard">
                    <input
                        x-ref="scan"
                        type="text"
                        name="scan"
                        wire:model="scan"
                        class="hr-scan__input"
                        autocomplete="off"
                        inputmode="numeric"
                        autofocus
                        placeholder="SC-12345678"
                        aria-label="رقم بطاقة التأمين"
                    >
                    <button type="submit" class="hr-card-action">إضافة</button>
                    <button type="button" class="hr-card-action" x-on:click="clearCache()">حذف الذاكرة</button>
                </form>
            </div>
        </section>

        @if ($groups === [])
            <section class="hr-panel">
                <div class="hr-panel__body">
                    <div class="hr-empty">
                        <p class="hr-empty__title">لا توجد بطاقات ممسوحة بعد</p>
                        <p class="hr-empty__text">امسح بطاقة الموظف أو أحد أفراد العائلة. ستظهر العائلة هنا مجمّعة.</p>
                    </div>
                </div>
            </section>
        @else
            <div class="hr-scan-groups">
                @foreach ($groups as $group)
                    <section @class(['hr-panel hr-scan-group', 'hr-scan-group--complete' => $group['complete']])>
                        <div class="hr-panel__head hr-scan-group__head">
                            <div>
                                <h3 class="hr-panel__title">{{ $group['employee_name'] }}</h3>
                                <p class="hr-panel__meta">
                                    مسح {{ $group['scanned_count'] }} من {{ $group['expected_count'] }}
                                    @if (filled($group['reference']))
                                        · {{ $group['reference'] }}
                                    @endif
                                </p>
                            </div>
                            <div class="hr-card-actions">
                                @if ($group['complete'])
                                    <span class="hr-chip hr-chip--approved">مكتملة</span>
                                @else
                                    <span class="hr-chip hr-chip--editing">ناقص {{ $group['expected_count'] - $group['scanned_count'] }}</span>
                                @endif
                                @if (filled($group['registration_url']))
                                    <a href="{{ $group['registration_url'] }}" class="hr-card-action">الطلب</a>
                                @endif
                                <button
                                    type="button"
                                    class="hr-card-action"
                                    wire:click="removeGroup({{ $group['employee_id'] }})"
                                >
                                    إزالة
                                </button>
                            </div>
                        </div>
                        <div class="hr-panel__body hr-scan-group__body">
                            @foreach ($group['members'] as $member)
                                <div @class(['hr-scan-member', 'hr-scan-member--scanned' => $member['scanned']])>
                                    <div class="hr-scan-member__who">
                                        <strong>{{ $member['name'] }}</strong>
                                        <span>{{ $member['role_label'] }}@if (filled($member['card_label']) && $member['card_label'] !== '—') · {{ $member['card_label'] }} @endif</span>
                                    </div>
                                    <div class="hr-scan-member__status">
                                        @if ($member['scanned'])
                                            <span class="hr-chip hr-chip--approved">تم المسح</span>
                                        @else
                                            <span class="hr-chip hr-chip--draft">لم يُمسح</span>
                                        @endif
                                        @if ($member['is_printed'])
                                            <span class="hr-chip hr-chip--submitted">مطبوعة</span>
                                        @endif
                                        @if ($member['scanned'] && $member['card_number'])
                                            <button
                                                type="button"
                                                class="hr-card-action"
                                                wire:click="removeCard({{ \Illuminate\Support\Js::from($member['card_number']) }})"
                                            >
                                                إزالة
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
