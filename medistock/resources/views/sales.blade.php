@extends('layouts.app')

@section('content')
        <!-- action buttons -->
        <div class="action-group">
            <a href="{{ route('sales.new') }}" class="btn btn-primary">+ New Sale</a>
            <a href="{{ route('transactions') }}" class="btn">Transaction History</a>
            <a href="{{ route('receipt') }}" class="btn">View Last Receipt</a>
        </div>

        <!-- 4 cards -->
        <div class="kpi-row">
            <div class="card kpi-card">
                <span class="kpi-label">Today's Sales</span>
                <span class="kpi-value">₱ {{ number_format($kpi['today_sales']) }}</span>
                <span class="pill {{ $today_class }}">{{ $today_text }}</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">This Week</span>
                <span class="kpi-value">₱ {{ number_format($kpi['week_sales']) }}</span>
                <span class="pill {{ $week_class }}">{{ $week_text }}</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">This Month</span>
                <span class="kpi-value">₱ {{ number_format($kpi['month_sales']) }}</span>
                <span class="pill {{ $month_class }}">{{ $month_text }}</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Items Sold Today</span>
                <span class="kpi-value">{{ number_format($kpi['units_today']) }}</span>
                <span class="pill {{ $units_class }}">{{ $units_text }}</span>
            </div>
        </div>

        <!-- chart and payment methods -->
        <div class="charts-row">
            <div class="card chart-card">
                <div class="card-head">
                    <h3 class="card-title">Sales by Day (This Week)</h3>
                    <span class="card-note card-note-green">₱ {{ number_format($kpi['week_sales']) }} total</span>
                </div>
                <div class="chart-box">
                    <canvas id="weekChart"></canvas>
                </div>
            </div>

            <div class="card chart-card">
                <div class="card-head">
                    <h3 class="card-title">Payment Methods</h3>
                    <span class="card-note">Last 30 days</span>
                </div>
                <div class="payment-list">
                    @if (count($payment_rows) == 0)
                        <span class="card-note">No sales yet</span>
                    @endif

                    @foreach ($payment_rows as $row)
                        @php
$method = $row['payment_method'];

                        if (isset($pay_names[$method])) {
                            $method_name = $pay_names[$method];
                        } else {
                            $method_name = $method;
                        }

                        if (isset($pay_colors[$method])) {
                            $method_color = $pay_colors[$method];
                        } else {
                            $method_color = "#94a3b8";
                        }
@endphp
                        <div class="pay-row">
                            <span class="pay-dot" style="background-color: {{ $method_color }}"></span>
                            <span class="pay-name">{{ $method_name }}</span>
                            <span class="pay-percent">{{ $row['percent'] }}%</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- recent transactions table -->
        <div class="card table-card">
            <h3 class="card-title">Recent Transactions</h3>
            <div class="table-wrap">
                <table class="data-table sales-table">
                    <tr>
                        <th>ORDER ID</th>
                        <th>DATE</th>
                        <th>ITEMS</th>
                        <th>CUSTOMER</th>
                        <th>PAYMENT</th>
                        <th>TOTAL</th>
                    </tr>

                    @if (count($sales_rows) == 0)
                        <tr><td colspan="6" class="empty-row">No transactions yet</td></tr>
                    @endif

                    @foreach ($sales_rows as $row)
                        @php
if ($row['item_count'] == 1) {
                            $items_text = "1 item";
                        } else {
                            $items_text = $row['item_count'] . " items";
                        }
@endphp
                        <tr>
                            <td class="cell-strong">{{ $row['order_no'] }}</td>
                            <td>{{ date("M j, Y", strtotime($row['sale_date'])) }}</td>
                            <td>{{ $items_text }}</td>
                            <td>{{ $row['customer_name'] }}</td>
                            <td>{{ $row['payment_method'] }}</td>
                            <td class="cell-semibold">₱ {{ number_format($row['total_amount'], 2) }}</td>
                        </tr>
                    @endforeach
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
var dayNames = {!! json_encode($day_names) !!};
var dayValues = {!! json_encode($day_values) !!};

// bar chart: sales by day (the highest day is dark teal)
var maxValue = Math.max.apply(null, dayValues);
var barColors = [];
for (var i = 0; i < dayValues.length; i++) {
    if (dayValues[i] == maxValue && maxValue > 0) {
        barColors.push("#0d9488");
    } else {
        barColors.push("#edfaf8");
    }
}

var weekChart = new Chart(document.getElementById("weekChart"), {
    type: "bar",
    data: {
        labels: dayNames,
        datasets: [{
            data: dayValues,
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
                    label: function (item) {
                        return "₱ " + item.parsed.y.toLocaleString();
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
