@php
    $preview = $this->preview;
    $sections = $preview === null ? [] : [
        ['key' => 'delivered', 'title' => 'تم التسليم', 'status' => 'success', 'detail' => 'workplace'],
        ['key' => 'ready', 'title' => 'جاهز للتسليم', 'status' => 'success', 'detail' => 'workplace'],
        ['key' => 'blocked', 'title' => 'لن يُسلَّم (يحتاج إصلاح)', 'status' => 'danger', 'detail' => 'problem'],
        ['key' => 'not_found', 'title' => 'رقم غير موجود', 'status' => 'danger', 'detail' => 'problem'],
        ['key' => 'already_delivered', 'title' => 'مُسلَّم سابقاً', 'status' => 'info', 'detail' => null],
        ['key' => 'repeated', 'title' => 'مكرر في القائمة', 'status' => 'info', 'detail' => null],
    ];
@endphp

<x-filament-panels::page>
    {{ $this->content }}

    @if ($this->deliveredNotice)
        <section class="hr-deliver-done" role="status" dir="rtl">
            <p class="hr-deliver-done__title">تم تسليم <span>{{ $this->deliveredNotice['count'] }}</span> عائلة بنجاح</p>
            <p class="hr-deliver-done__meta">إلى {{ $this->deliveredNotice['recipient'] }}</p>
        </section>
    @endif

    @if ($preview !== null)
        <div dir="rtl" class="hr-review">
            <section class="hr-panel">
                <div class="hr-panel__body">
                    <div class="hr-kpis hr-kpis--report">
                        @foreach ($sections as $section)
                            @if ($section['key'] !== 'delivered' || isset($preview['delivered']))
                                <div class="hr-kpi">
                                    <span class="hr-kpi__label">{{ $section['title'] }}</span>
                                    <div class="hr-kpi__value">{{ count($preview[$section['key']] ?? []) }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </section>

            @foreach ($sections as $section)
                @php($items = $preview[$section['key']] ?? [])
                @if ($items !== [])
                    <section class="hr-panel">
                        <div class="hr-panel__head">
                            <h3 class="hr-panel__title">{{ $section['title'] }}</h3>
                            <span class="hr-report-status--{{ $section['status'] }}">{{ count($items) }}</span>
                        </div>
                        <div class="hr-panel__body">
                            <div class="hr-report-table-wrap">
                                <table class="hr-med-table hr-report-table">
                                    <thead>
                                        <tr>
                                            <th>السطر</th>
                                            <th>الرقم الوظيفي</th>
                                            @if ($section['key'] !== 'not_found' && $section['key'] !== 'repeated')
                                                <th>الاسم</th>
                                            @endif
                                            @if ($section['detail'] === 'workplace')
                                                <th>الإدارة</th>
                                            @elseif ($section['detail'] === 'problem')
                                                <th>السبب</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($items as $item)
                                            <tr>
                                                <td>{{ $item['row'] }}</td>
                                                <td><strong>{{ $item['employee_number'] }}</strong></td>
                                                @if ($section['key'] !== 'not_found' && $section['key'] !== 'repeated')
                                                    <td>{{ $item['name'] }}</td>
                                                @endif
                                                @if ($section['detail'] !== null)
                                                    <td>{{ $item[$section['detail']] }}</td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                @endif
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
