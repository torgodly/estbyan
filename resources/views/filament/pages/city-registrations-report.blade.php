@php
    $report = $this->report;
    $maxTotal = collect($report['cities'])->max('total') ?: 1;
@endphp

<x-filament-panels::page>
    <div dir="rtl" class="hr-review">
        <div class="hr-review__main">
            <section class="hr-panel">
                <div class="hr-panel__body">
                    <p class="hr-report-lead">
                        عدد المقبول والمرفوض وكل حالات التسجيل في كل مدينة.
                        يشمل كل الطلبات بما فيها المسودات.
                    </p>

                    <div class="hr-kpis hr-kpis--report hr-kpis--cities">
                        <div class="hr-kpi">
                            <span class="hr-kpi__label">الإجمالي</span>
                            <div class="hr-kpi__value">{{ $report['totals']['all'] }}</div>
                        </div>
                        @foreach ($report['statuses'] as $status)
                            <div class="hr-kpi">
                                <span class="hr-kpi__label">{{ $status['label'] }}</span>
                                <div class="hr-kpi__value hr-report-status hr-report-status--{{ $status['color'] }}">
                                    {{ $report['totals'][$status['value']] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="hr-panel">
                <div class="hr-panel__head">
                    <h3 class="hr-panel__title">التوزيع حسب المدينة</h3>
                    <span class="hr-panel__meta">{{ count($report['cities']) }} مدينة · مرتبة حسب عدد الطلبات</span>
                </div>
                <div class="hr-panel__body">
                    <div class="hr-report-table-wrap">
                        <table class="hr-med-table hr-report-table">
                            <thead>
                                <tr>
                                    <th>المدينة</th>
                                    @foreach ($report['statuses'] as $status)
                                        <th>{{ $status['label'] }}</th>
                                    @endforeach
                                    <th>الإجمالي</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['cities'] as $city)
                                    @php
                                        $bar = (int) round(($city['total'] / $maxTotal) * 100);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="hr-report-disease">
                                                <strong>{{ $city['label'] }}</strong>
                                                <span class="hr-report-bar" aria-hidden="true">
                                                    <span class="hr-report-bar__fill" style="width: {{ $bar }}%"></span>
                                                </span>
                                            </div>
                                        </td>
                                        @foreach ($report['statuses'] as $status)
                                            <td>
                                                <span class="hr-report-count hr-report-status hr-report-status--{{ $status['color'] }}">
                                                    {{ $city['counts'][$status['value']] }}
                                                </span>
                                            </td>
                                        @endforeach
                                        <td>
                                            <span class="hr-report-count">{{ $city['total'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>الإجمالي</th>
                                    @foreach ($report['statuses'] as $status)
                                        <th>{{ $report['totals'][$status['value']] }}</th>
                                    @endforeach
                                    <th>{{ $report['totals']['all'] }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
