@php
    $result = $this->result();
@endphp

<x-filament-panels::page>
    <div dir="rtl" class="hr-review hr-deliver">
        <section class="hr-panel">
            <div class="hr-panel__head">
                <div>
                    <h3 class="hr-panel__title">البحث بالرقم الوطني</h3>
                    <p class="hr-panel__meta">رقم الموظف الوطني أو رقم أحد أفراد عائلته.</p>
                </div>
                <span class="hr-chip">للعرض فقط</span>
            </div>
            <div class="hr-panel__body hr-deliver__scan-body">
                <form class="hr-deliver__scan" wire:submit="search">
                    <input
                        type="text"
                        name="nationalId"
                        wire:model="nationalId"
                        class="hr-deliver__input"
                        autocomplete="off"
                        inputmode="numeric"
                        autofocus
                        placeholder="الرقم الوطني"
                        aria-label="الرقم الوطني"
                    >
                    <button type="submit" class="hr-card-action">استعلام</button>
                    @if ($this->searchedNationalId)
                        <button type="button" class="hr-card-action" wire:click="clear">مسح</button>
                    @endif
                </form>
            </div>
        </section>

        @if ($this->searchedNationalId && $result === null)
            <section class="hr-panel">
                <div class="hr-panel__body">
                    <div class="hr-empty">
                        <p class="hr-empty__title">لا توجد نتائج</p>
                        <p class="hr-empty__text">لا يوجد موظف أو فرد عائلة بالرقم الوطني {{ $this->searchedNationalId }}.</p>
                    </div>
                </div>
            </section>
        @elseif ($result)
            <section class="hr-panel">
                <div class="hr-panel__head">
                    <div>
                        <p class="hr-deliver-family__label">الرقم يخص: {{ $result['matched_as'] }}</p>
                        <h3 class="hr-panel__title">{{ $result['matched_name'] }}</h3>
                    </div>
                    <div class="hr-card-actions">
                        @if ($result['registration'])
                            <span class="hr-chip">{{ $result['registration']['حالة الطلب'] }}</span>
                        @else
                            <span class="hr-chip hr-chip--draft">لم يسجّل بعد</span>
                        @endif
                        <span @class(['hr-chip', 'hr-chip--approved' => $result['cards']['printed'], 'hr-chip--draft' => ! $result['cards']['printed']])>
                            {{ $result['cards']['printed'] ? 'بطاقة الموظف مطبوعة' : 'بطاقة الموظف غير مطبوعة' }}
                        </span>
                        <span @class(['hr-chip', 'hr-chip--submitted' => $result['cards']['delivered'], 'hr-chip--draft' => ! $result['cards']['delivered']])>
                            {{ $result['cards']['delivered'] ? 'مُسلّمة' : 'لم تُسلّم' }}
                        </span>
                    </div>
                </div>
                @if ($result['cards']['delivered'] && filled($result['cards']['delivery_label']))
                    <div class="hr-deliver-status">
                        <strong>تم تسليم البطاقات</strong>
                        <span>{{ $result['cards']['delivery_label'] }}</span>
                    </div>
                @endif
                <div class="hr-panel__body">
                    <div class="hr-rows">
                        @foreach ($result['employee'] + ($result['registration'] ?? []) as $label => $value)
                            <div class="hr-row">
                                <div class="hr-row__label">{{ $label }}</div>
                                <div class="hr-row__value">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="hr-panel hr-deliver-family">
                <div class="hr-panel__head">
                    <div>
                        <h3 class="hr-panel__title">أفراد العائلة</h3>
                        <p class="hr-panel__meta">{{ count($result['family']) }} فرد</p>
                    </div>
                </div>
                <div class="hr-panel__body hr-deliver-family__body">
                    @forelse ($result['family'] as $member)
                        <div @class(['hr-deliver-member', 'hr-deliver-member--scanned' => $member['matched']])>
                            <div class="hr-deliver-member__who">
                                <strong>{{ $member['name'] }}</strong>
                                <span>
                                    {{ $member['relationship'] }}
                                    · {{ $member['identity'] }}
                                    · {{ $member['date_of_birth'] }}
                                </span>
                            </div>
                            <div class="hr-deliver-member__status">
                                @if ($member['matched'])
                                    <span class="hr-chip hr-chip--submitted">الرقم المبحوث عنه</span>
                                @endif
                                <span @class(['hr-chip', 'hr-chip--approved' => $member['printed'], 'hr-chip--draft' => ! $member['printed']])>
                                    {{ $member['printed'] ? 'مطبوعة' : 'غير مطبوعة' }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <div class="hr-empty">
                            <p class="hr-empty__title">لا يوجد أفراد عائلة مسجلون</p>
                        </div>
                    @endforelse
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
