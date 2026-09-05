@php
    $report = $this->report;
    $maxTotal = collect($report['conditions'])->max('total') ?: 1;
    $hasCases = collect($report['conditions'])->contains(fn (array $condition): bool => $condition['total'] > 0);
@endphp

<x-filament-panels::page>
    <div dir="rtl" class="hr-review">
        <div class="hr-review__main">
            <section class="hr-panel">
                <div class="hr-panel__body">
                    <p class="hr-report-lead">
                        تقرير شامل عن الأمراض المزمنة للموظفين المسجّلين وأفراد عائلاتهم.
                        يشمل الطلبات المُرسلة والمقبولة والمرفوضة وقيد التعديل، ولا يشمل المسودات.
                    </p>

                    <div class="hr-kpis hr-kpis--report">
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">الموظفون المسجّلون</span>
                            <div class="hr-kpi__value">{{ $report['registered_employees'] }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">موظفون بمرض مزمن</span>
                            <div class="hr-kpi__value">{{ $report['employees_with_chronic'] }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">أفراد عائلة بمرض مزمن</span>
                            <div class="hr-kpi__value">{{ $report['family_with_chronic'] }}</div>
                        </div>
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">إجمالي المصابين</span>
                            <div class="hr-kpi__value">{{ $report['total_with_chronic'] }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="hr-panel">
                <div class="hr-panel__head">
                    <h3 class="hr-panel__title">توزيع الأمراض المزمنة</h3>
                    <span class="hr-panel__meta">نسبة الموظفين من الموظفين، ونسبة العائلة من العائلة، والجميع من الموظفين والعائلة معاً ({{ $report['registered_people'] }})</span>
                </div>
                <div class="hr-panel__body">
                    @if (! $hasCases)
                        <p class="hr-report-empty">لا توجد أمراض مزمنة مسجّلة حتى الآن.</p>
                    @else
                        <div class="hr-report-table-wrap">
                            <table class="hr-med-table hr-report-table">
                                <thead>
                                    <tr>
                                        <th>المرض</th>
                                        <th>موظفون</th>
                                        <th>عائلة</th>
                                        <th>الجميع</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['conditions'] as $condition)
                                        @php
                                            $bar = (int) round(($condition['total'] / $maxTotal) * 100);
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="hr-report-disease">
                                                    <strong>{{ $condition['label'] }}</strong>
                                                    <span class="hr-report-bar" aria-hidden="true">
                                                        <span class="hr-report-bar__fill" style="width: {{ $bar }}%"></span>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="hr-report-count">{{ $condition['employees'] }}</span>
                                                <span class="hr-report-share">{{ number_format($condition['employee_share'], 1) }}% من الموظفين</span>
                                            </td>
                                            <td>
                                                <span class="hr-report-count">{{ $condition['family'] }}</span>
                                                <span class="hr-report-share">{{ number_format($condition['family_share'], 1) }}% من العائلة</span>
                                            </td>
                                            <td>
                                                <span class="hr-report-count">{{ $condition['total'] }}</span>
                                                <span class="hr-report-share">{{ number_format($condition['share'], 1) }}% من الجميع</span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
