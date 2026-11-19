@extends('layouts.app')

@section('content')
        <!-- 4 cards -->
        <div class="kpi-row">
            <div class="card kpi-card">
                <span class="kpi-label">Today’s Sales</span>
                <span class="kpi-value">₱ {{ number_format($kpi['today_sales'], 2) }}</span>
                <span class="pill {{ $change_class }}">{{ $change_text }}</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Total Medicines</span>
                <span class="kpi-value">{{ $kpi['total_medicines'] }}</span>
                <span class="pill pill-categories">{{ $kpi['total_categories'] }} categories</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Low-Stock Items</span>
                <span class="kpi-value">{{ $kpi['low_stock'] }}</span>
                <span class="pill pill-out">{{ $kpi['out_of_stock'] }} out of stock</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Overstock Items</span>
                <span class="kpi-value">{{ $kpi['overstock'] }} items</span>
                <span class="pill pill-overstock">Above max stock level</span>
            </div>
        </div>

        <!-- 2 charts -->
        <div class="charts-row">
            <div class="card chart-card">
                <div class="card-head">
                    <h3 class="card-title">Sales Trend (Last 30 Days)</h3>
                    <span class="card-note card-note-green">{{ $total_30_text }}</span>
                </div>
                <div class="chart-box">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            <div class="card chart-card">
                <div class="card-head">
                    <h3 class="card-title">Top Medicine Demand</h3>
                    <span class="card-note">This week</span>
                </div>
                <div class="chart-box">
                    <canvas id="demandChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 2 tables -->
        <div class="tables-row">
            <div class="card table-card">
                <h3 class="card-title">Low Stock &amp; Expiring Soon</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <tr>
                            <th>MEDICINE</th>
                            <th>QTY</th>
                            <th>STATUS</th>
                        </tr>
                        @if (count($low_rows) == 0)
                            <tr><td colspan="3" class="empty-row">Nothing to show</td></tr>
                        @endif
                        @foreach ($low_rows as $row)
                            @php
if ($row['status'] == "Expiring Soon") {
                                $status_class = "status-amber";
                            } else {
                                $status_class = "status-red";
                            }
@endphp
                            <tr>
                                <td class="cell-strong">{{ $row['medicine_name'] }}</td>
                                <td>{{ $row['quantity'] . " " . $row['unit'] }}</td>
                                <td class="{{ $status_class }}">{{ $row['status'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>

            <div class="card table-card">
                <h3 class="card-title">Recent Sales</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <tr>
                            <th>ORDER</th>
                            <th>CUSTOMER</th>
                            <th>TOTAL</th>
                        </tr>
                        @if (count($sales_rows) == 0)
                            <tr><td colspan="3" class="empty-row">No sales yet</td></tr>
                        @endif
                        @foreach ($sales_rows as $row)
                            <tr>
                                <td class="cell-strong">{{ $row['order_no'] }}</td>
                                <td>{{ $row['customer_name'] }}</td>
                                <td class="cell-bold">₱ {{ number_format($row['total_amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
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
var trendLabels = {!! json_encode($trend_labels) !!};
var trendValues = {!! json_encode($trend_values) !!};
var demandLabels = {!! json_encode($demand_labels) !!};
var demandNames = {!! json_encode($demand_names) !!};
var demandValues = {!! json_encode($demand_values) !!};

// 7-day average: for every day, the average of that day and the 6 days before it
var averageValues = [];
for (var i = 0; i < trendValues.length; i++) {
    var sum = 0;
    var count = 0;
    for (var j = Math.max(0, i - 6); j <= i; j++) {
        sum += trendValues[j];
        count++;
    }
    averageValues.push(Math.round(sum / count * 100) / 100);
}

// line chart: sales trend (daily sales + the smoother 7-day average)
var salesChart = new Chart(document.getElementById("salesChart"), {
    type: "line",
    data: {
        labels: trendLabels,
        datasets: [
            {
                label: "Daily sales",
                data: trendValues,
                borderColor: "#5eead4",
                backgroundColor: "rgba(20, 184, 166, 0.12)",
                borderWidth: 2,
                fill: true,
                pointRadius: 3,
                pointBackgroundColor: "#14b8a6",
                pointHoverRadius: 5,
                tension: 0.3
            },
            {
                label: "7-day average",
                data: averageValues,
                borderColor: "#0f766e",
                borderWidth: 3,
                borderDash: [6, 4],
                fill: false,
                pointRadius: 0,
                pointHoverRadius: 4,
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: "index", intersect: false },
        plugins: {
            legend: {
                display: true,
                position: "bottom",
                labels: { boxWidth: 14, boxHeight: 3, usePointStyle: false, font: { size: 11 } }
            },
            tooltip: {
                callbacks: {
                    label: function (item) {
                        return item.dataset.label + ": ₱ " + item.parsed.y.toLocaleString();
                    }
                }
            }
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { maxTicksLimit: 8, maxRotation: 0, font: { size: 11 }, color: "#64748b" }
            },
            y: {
                beginAtZero: true,
                grid: { color: "#f1f5f9" },
                ticks: {
                    maxTicksLimit: 5,
                    font: { size: 11 },
                    color: "#64748b",
                    callback: function (value) {
                        return "₱" + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// bar chart: top medicine demand (the highest bar is dark teal)
var maxValue = Math.max.apply(null, demandValues);
var barColors = [];
for (var i = 0; i < demandValues.length; i++) {
    if (demandValues[i] == maxValue) {
        barColors.push("#0d9488");
    } else {
        barColors.push("#edfaf8");
    }
}

var demandChart = new Chart(document.getElementById("demandChart"), {
    type: "bar",
    data: {
        labels: demandLabels,
        datasets: [{
            data: demandValues,
            backgroundColor: barColors,
            borderRadius: 4,
            maxBarThickness: 24
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: function (items) {
                        return demandNames[items[0].dataIndex];
                    },
                    label: function (item) {
                        return item.parsed.y + " units sold";
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
