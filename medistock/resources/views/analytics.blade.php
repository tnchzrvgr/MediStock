@extends('layouts.app')

@section('content')
        <!-- 4 cards -->
        <div class="kpi-row">
            <div class="card kpi-card">
                <span class="kpi-label">Sales This Month</span>
                <span class="kpi-value">₱ {{ number_format($kpi['month_sales']) }}</span>
                <span class="pill {{ $growth_class }}">{{ $growth_text }}</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Running Out Soon</span>
                <span class="kpi-value">{{ medicine_count_text($running_out) }}</span>
                <span class="pill {{ $running_out > 0 ? 'pill-down' : 'pill-up' }}">{{ $running_out > 0 ? 'Will run out within 2 weeks' : 'No medicine running out' }}</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Expected Sales Next Week</span>
                <span class="kpi-value">₱ {{ number_format($expected_next_week) }}</span>
                <span class="pill pill-teal">Based on the last 8 weeks</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Best Seller</span>
                <span class="kpi-value">{{ $best_name }}</span>
                <span class="pill pill-blue">{{ number_format($best_units) }} sold in 30 days</span>
            </div>
        </div>

        <!-- forecast chart and sales by category -->
        <div class="charts-row">
            <div class="card chart-card">
                <div class="card-head">
                    <h3 class="card-title">Demand Forecast — Next 30 Days</h3>
                    <span class="card-note card-note-green">{{ $forecast_name }}</span>
                </div>
                <div class="chart-box">
                    <canvas id="forecastChart"></canvas>
                </div>
            </div>

            <div class="card chart-card">
                <h3 class="card-title">Sales by Category</h3>
                <div class="cat-list">
                    @if (count($categories) == 0)
                        <span class="card-note">No sales yet</span>
                    @endif

                    @foreach ($categories as $cat)
                        <div>
                            <div class="cat-head">
                                <span class="cat-name">{{ $cat[0] }}</span>
                                <span class="cat-percent">{{ $cat[1] }}%</span>
                            </div>
                            <div class="bar-track">
                                <div class="bar-fill" style="width: {{ $cat[1] }}%; background-color: {{ $cat[2] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <h2 class="section-title">Inventory Insights</h2>

        <!-- period comparison and inventory value -->
        <div class="charts-row">
            <div class="card">
                <div class="card-head align-center">
                    <div>
                        <h3 class="card-title">Period Comparison</h3>
                        <p class="card-sub">{{ $compare_sub }}</p>
                    </div>
                    <div class="segmented">
                        <a href="{{ route('analytics') }}?period=daily" class="seg-btn {{ ($period == 'daily') ? 'active' : '' }}">Daily</a>
                        <a href="{{ route('analytics') }}?period=weekly" class="seg-btn {{ ($period == 'weekly') ? 'active' : '' }}">Weekly</a>
                        <a href="{{ route('analytics') }}?period=monthly" class="seg-btn {{ ($period == 'monthly') ? 'active' : '' }}">Monthly</a>
                    </div>
                </div>

                <div class="table-wrap">
                    <table class="data-table compare-table">
                        <tr>
                            <th>METRIC</th>
                            <th>{{ $now_label }}</th>
                            <th>{{ $prev_label }}</th>
                            <th>CHANGE</th>
                        </tr>

                        @foreach ($compare_list as $item)
                            @php
if ($item[4] > 0) {
                                $change = (($item[3] - $item[4]) / $item[4]) * 100;
                                if ($change >= 0) {
                                    $change_text = "▲ " . number_format($change, 1) . "%";
                                    $change_class = "change-up";
                                } else {
                                    $change_text = "▼ " . number_format(abs($change), 1) . "%";
                                    $change_class = "change-warn";
                                }
                            } else {
                                $change_text = "—";
                                $change_class = "";
                            }
@endphp
                            <tr>
                                <td class="cell-strong">{{ $item[0] }}</td>
                                <td>{{ $item[1] }}</td>
                                <td>{{ $item[2] }}</td>
                                <td class="{{ $change_class }}">{{ $change_text }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>

            <div class="card">
                <h3 class="card-title">Inventory Value Summary</h3>
                <span class="big-number">₱ {{ number_format($inventory_total) }}</span>
                <span class="big-sub">Estimated cash tied up in stock</span>

                @foreach ($value_top as $row)
                    <div class="value-row">
                        <span class="value-name">{{ $row['category_name'] }}</span>
                        <span class="value-amount">₱ {{ number_format($row['total_value']) }}</span>
                    </div>
                @endforeach

                @if (count($value_rows) > 4)
                    <div class="value-row">
                        <span class="value-name">Other categories</span>
                        <span class="value-amount">₱ {{ number_format($value_other) }}</span>
                    </div>
                @endif

                <div class="dead-callout">
                    <span>Tied up in non-moving stock</span>
                    <strong>₱ {{ number_format($dead_value) }}</strong>
                </div>
            </div>
        </div>

        <!-- fast / slow / non-moving -->
        <div class="card">
            <div class="card-head">
                <h3 class="card-title">Fast vs. Slow vs. Non-moving Medicines</h3>
                <span class="card-note">Based on the last 90 days of sales</span>
            </div>

            <div class="movement-cols">
                <div class="move-box move-fast">
                    <span class="move-title">Fast-moving</span>
                    <span class="move-count">{{ medicine_count_text(count($groups['Fast-moving'])) }}</span>
                    <span class="move-desc">Sells 50+ units per month</span>
                    @for ($i = 0; $i < count($groups['Fast-moving']) && $i < 3; $i++)
                        <span class="move-item">• {{ $groups['Fast-moving'][$i]['medicine_name'] }}</span>
                    @endfor
                </div>

                <div class="move-box move-slow">
                    <span class="move-title">Slow-moving</span>
                    <span class="move-count">{{ medicine_count_text(count($groups['Slow-moving'])) }}</span>
                    <span class="move-desc">Sells 1–49 units per month</span>
                    @for ($i = 0; $i < count($groups['Slow-moving']) && $i < 3; $i++)
                        <span class="move-item">• {{ $groups['Slow-moving'][$i]['medicine_name'] }}</span>
                    @endfor
                </div>

                <div class="move-box move-dead">
                    <span class="move-title">Non-moving (dead inventory)</span>
                    <span class="move-count">{{ medicine_count_text(count($groups['Non-moving'])) }}</span>
                    <span class="move-desc">No sales in 90 days · ₱ {{ number_format($dead_value) }} tied up</span>
                    @for ($i = 0; $i < count($groups['Non-moving']) && $i < 3; $i++)
                        <span class="move-item">• {{ $groups['Non-moving'][$i]['medicine_name'] }}</span>
                    @endfor
                </div>
            </div>
        </div>

        <!-- best selling medicines -->
        <div class="card table-card">
            <h3 class="card-title">Best-Selling Medicines (Top 10)</h3>
            <div class="table-wrap">
                <table class="data-table top-table">
                    <tr>
                        <th>RANK</th>
                        <th>MEDICINE</th>
                        <th>CATEGORY</th>
                        <th>UNITS SOLD</th>
                        <th>REVENUE</th>
                        <th>TREND</th>
                    </tr>

                    @if (count($best_rows) == 0)
                        <tr><td colspan="6" class="empty-row">No sales yet</td></tr>
                    @endif

                    @for ($i = 0; $i < count($best_rows); $i++)
                        @php
$row = $best_rows[$i];

                        // trend = last 30 days compared with the 30 days before
                        if ($row['units_prev'] > 0) {
                            $trend = (($row['units_now'] - $row['units_prev']) / $row['units_prev']) * 100;
                            if ($trend >= 0) {
                                $trend_text = "▲ " . round($trend) . "%";
                                $trend_class = "change-up";
                            } else {
                                $trend_text = "▼ " . round(abs($trend)) . "%";
                                $trend_class = "change-down";
                            }
                        } else {
                            $trend_text = "New";
                            $trend_class = "change-up";
                        }
@endphp
                        <tr>
                            <td class="cell-strong">{{ $i + 1 }}</td>
                            <td>{{ $row['medicine_name'] }}</td>
                            <td>{{ $row['category_name'] }}</td>
                            <td>{{ number_format($row['units_now']) }}</td>
                            <td>₱ {{ number_format($row['revenue_now']) }}</td>
                            <td class="{{ $trend_class }}">{{ $trend_text }}</td>
                        </tr>
                    @endfor
                </table>
            </div>
        </div>
@endsection

@push('scripts')
<script src="{{ asset('js/chart.umd.js') }}"></script>
<script>
// if the local file is not there, use the internet copy
if (typeof Chart == "undefined") {
    document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js"><\/script>');
}
</script>
<script>

// data from PHP
var chartLabels = {!! json_encode($chart_labels) !!};
var actualData = {!! json_encode($actual_data) !!};
var forecastData = {!! json_encode($forecast_data) !!};

// line chart: real demand (teal) and forecasted demand (orange dashed)
var forecastChart = new Chart(document.getElementById("forecastChart"), {
    type: "line",
    data: {
        labels: chartLabels,
        datasets: [
            {
                label: "Actual demand",
                data: actualData,
                borderColor: "#0d9488",
                backgroundColor: "#0d9488",
                borderWidth: 2,
                pointRadius: 3,
                tension: 0.3
            },
            {
                label: "Forecasted demand",
                data: forecastData,
                borderColor: "#d97706",
                backgroundColor: "#d97706",
                borderWidth: 2,
                borderDash: [5, 5],
                pointRadius: 3,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: "bottom",
                align: "start",
                labels: {
                    usePointStyle: true,
                    boxWidth: 8,
                    color: "#475569",
                    font: { size: 11, weight: "500" }
                }
            },
            tooltip: {
                callbacks: {
                    label: function (item) {
                        return item.dataset.label + ": " + item.parsed.y + " units";
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: { color: "#94a3b8", font: { size: 9, weight: "500" } }
            },
            y: {
                display: false,
                beginAtZero: true
            }
        }
    }
});
</script>
@endpush
