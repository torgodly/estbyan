@php
    $family = $this->family();
@endphp

<x-filament-panels::page>
    <div
        dir="rtl"
        class="hr-review hr-deliver"
        x-data
        x-on:insurance-card-scanned.window="$nextTick(() => { $refs.scan?.focus(); $refs.scan?.select(); })"
    >
        <section class="hr-panel">
            <div class="hr-panel__head">
                <div>
                    <h3 class="hr-panel__title">تسليم عائلة واحدة</h3>
                    <p class="hr-panel__meta">
                        @if ($family)
                            أكمل بطاقات هذه العائلة، ثم سجّل التسليم. يجب مسح كل أفراد العائلة بما فيهم الأب والأم.
                        @else
                            امسح أي بطاقة من العائلة للبدء. لا يمكن فتح عائلة ثانية قبل إنهاء الحالية.
                        @endif
                    </p>
                </div>
                @if ($family)
                    <span @class(['hr-chip', 'hr-chip--approved' => $family['complete'], 'hr-chip--editing' => ! $family['complete']])>
                        {{ $family['scanned_count'] }} / {{ $family['expected_count'] }}
                    </span>
                @else
                    <span class="hr-chip">جاهز للمسح</span>
                @endif
            </div>
            <div class="hr-panel__body hr-deliver__scan-body">
                <form class="hr-deliver__scan" wire:submit="scanCard">
                    <input
                        x-ref="scan"
                        type="text"
                        name="scan"
                        wire:model="scan"
                        class="hr-deliver__input"
                        autocomplete="off"
                        inputmode="numeric"
                        autofocus
                        placeholder="SC-12345678"
                        aria-label="رقم بطاقة التأمين"
                    >
                    <button type="submit" class="hr-card-action">إضافة</button>
                </form>
            </div>
        </section>

        @if ($this->deliveryNotice)
            <section class="hr-deliver-done" role="status">
                <p class="hr-deliver-done__title">
                    تم تسليم بطاقات الموظف
                    <span>{{ $this->deliveryNotice['name'] }}</span>
                    بنجاح
                </p>
                <p class="hr-deliver-done__meta">إلى {{ $this->deliveryNotice['recipient'] }}</p>
            </section>
        @endif

        @if ($family === null)
            <section class="hr-panel">
                <div class="hr-panel__body">
                    <div class="hr-empty">
                        <p class="hr-empty__title">لا توجد عائلة قيد التسليم</p>
                        <p class="hr-empty__text">امسح بطاقة الموظف أو أحد أفراد العائلة. بعد اكتمال كل البطاقات اضغط «تم التسليم» واختر جهة الاستلام.</p>
                    </div>
                </div>
            </section>
        @else
            <section @class(['hr-panel hr-deliver-family', 'hr-deliver-family--complete' => $family['complete'], 'hr-deliver-family--delivered' => $family['is_delivered']])>
                <div class="hr-panel__head">
                    <div>
                        <p class="hr-deliver-family__label">العائلة الحالية</p>
                        <h3 class="hr-panel__title">{{ $family['employee_name'] }}</h3>
                        <p class="hr-panel__meta">
                            مسح {{ $family['scanned_count'] }} من {{ $family['expected_count'] }}
                            @if (filled($family['reference']))
                                · {{ $family['reference'] }}
                            @endif
                        </p>
                    </div>
                    <div class="hr-card-actions">
                        @if ($family['is_delivered'])
                            <span class="hr-chip hr-chip--submitted">مُسلّمة</span>
                        @endif
                        @if ($family['complete'])
                            <span class="hr-chip hr-chip--approved">{{ $family['is_delivered'] ? 'للعرض فقط' : 'جاهزة للتسليم' }}</span>
                        @else
                            <span class="hr-chip hr-chip--editing">ناقص {{ $family['expected_count'] - $family['scanned_count'] }}</span>
                        @endif
                        @if (filled($family['registration_url']))
                            <a href="{{ $family['registration_url'] }}" class="hr-card-action">الطلب</a>
                        @endif
                    </div>
                </div>

                @if ($family['is_delivered'])
                    <div class="hr-deliver-status">
                        <strong>تم التسليم سابقاً — للعرض فقط</strong>
                        <span>
                            إلى {{ $family['delivered_to_label'] ?? '—' }}
                            @if (filled($family['delivered_at']))
                                · {{ $family['delivered_at'] }}
                            @endif
                            @if (filled($family['delivered_by_name']))
                                · بواسطة {{ $family['delivered_by_name'] }}
                            @endif
                        </span>
                    </div>
                @endif

                <div class="hr-deliver-progress" aria-hidden="true">
                    <span
                        class="hr-deliver-progress__bar"
                        style="width: {{ $family['expected_count'] > 0 ? round(($family['scanned_count'] / $family['expected_count']) * 100) : 0 }}%"
                    ></span>
                </div>

                <div class="hr-panel__body hr-deliver-family__body">
                    @foreach ($family['members'] as $member)
                        <div @class(['hr-deliver-member', 'hr-deliver-member--scanned' => $member['scanned']])>
                            <div class="hr-deliver-member__who">
                                <strong>{{ $member['name'] }}</strong>
                                <span>
                                    {{ $member['role_label'] }}
                                    @if (filled($member['card_label']) && $member['card_label'] !== '—')
                                        · {{ $member['card_label'] }}
                                    @endif
                                </span>
                            </div>
                            <div class="hr-deliver-member__status">
                                @if ($member['scanned'])
                                    <span class="hr-chip hr-chip--approved">تم المسح</span>
                                    @if ($member['card_number'])
                                        <button
                                            type="button"
                                            class="hr-card-action"
                                            wire:click="removeCard({{ \Illuminate\Support\Js::from($member['card_number']) }})"
                                        >
                                            إزالة
                                        </button>
                                    @endif
                                @else
                                    <span class="hr-chip hr-chip--draft">لم يُمسح</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
