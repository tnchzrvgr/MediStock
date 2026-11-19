@extends('layouts.app')

@section('content')
        <!-- 4 cards -->
        <div class="kpi-row">
            <div class="card kpi-card">
                <span class="kpi-label">Low-Stock Items</span>
                <span class="kpi-value">{{ number_format($kpi['low_count']) }}</span>
                <span class="pill pill-danger">At or below minimum</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Overstock Items</span>
                <span class="kpi-value">{{ number_format($kpi['over_count']) }}</span>
                <span class="pill pill-amber">Above maximum level</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Suggested Reorders</span>
                <span class="kpi-value">{{ number_format($kpi['reorder_count']) }}</span>
                <span class="pill pill-blue">Based on recent sales</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Near-Expiry (30 days)</span>
                <span class="kpi-value">{{ number_format($kpi['expiring_count']) }} batches</span>
                <span class="pill pill-amber">Needs review</span>
            </div>
        </div>

        <!-- low-stock and overstock lists -->
        <div class="two-col">
            <div class="card table-card">
                <h3 class="card-title">Low-Stock Alert List</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <tr>
                            <th>MEDICINE</th>
                            <th>CURRENT</th>
                            <th>MIN</th>
                            <th>STATUS</th>
                        </tr>

                        @if (count($low_list) == 0)
                            <tr><td colspan="4" class="empty-row">No low-stock items</td></tr>
                        @endif

                        @foreach ($low_list as $row)
                            @php
// Low is amber, Critical and Out of Stock are red
                            $color = "status-red";
                            if ($row['alert_status'] == "Low") {
                                $color = "status-amber";
                            }
@endphp
                            <tr>
                                <td class="cell-strong">{{ $row['medicine_name'] }}</td>
                                <td>{{ number_format($row['quantity']) }}</td>
                                <td>{{ number_format($row['reorder_level']) }}</td>
                                <td class="{{ $color }}">{{ $row['alert_status'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>

            <div class="card table-card">
                <h3 class="card-title">Overstock List</h3>
                <div class="table-wrap">
                    <table class="data-table">
                        <tr>
                            <th>MEDICINE</th>
                            <th>CURRENT</th>
                            <th>MAX</th>
                            <th>EXCESS</th>
                        </tr>

                        @if (count($over_list) == 0)
                            <tr><td colspan="4" class="empty-row">No overstocked items</td></tr>
                        @endif

                        @foreach ($over_list as $row)
                            @php
// excess of 2 times the maximum or more is red, the rest is amber
                            $color = "status-amber";
                            if ($row['excess'] >= $row['max_stock'] * 2) {
                                $color = "status-red";
                            }
@endphp
                            <tr>
                                <td class="cell-strong">{{ $row['medicine_name'] }}</td>
                                <td>{{ number_format($row['quantity']) }}</td>
                                <td>{{ number_format($row['max_stock']) }}</td>
                                <td class="{{ $color }}">+{{ number_format($row['excess']) }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </div>
        </div>

        <!-- suggested reorder list -->
        <div class="card table-card">
            <h3 class="card-title">Suggested Reorder List</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>MEDICINE</th>
                        <th>CURRENT STOCK</th>
                        <th>AVG. WEEKLY SALES</th>
                        <th>SUGGESTED QTY</th>
                        <th>SUPPLIER</th>
                    </tr>

                    @if (count($reorder_list) == 0)
                        <tr><td colspan="5" class="empty-row">Nothing to reorder right now</td></tr>
                    @endif

                    @foreach ($reorder_list as $row)
                        <tr>
                            <td class="cell-strong">{{ $row['medicine_name'] }}</td>
                            <td>{{ number_format($row['quantity']) }}</td>
                            <td>{{ number_format($row['weekly_sales']) . " " . unit_text($row['unit'], $row['weekly_sales']) }}</td>
                            <td class="status-green">+{{ number_format($row['suggested_qty']) . " " . unit_text($row['unit'], $row['suggested_qty']) }}</td>
                            <td>{{ $row['supplier'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>

        <!-- near-expiry list -->
        <div class="card table-card">
            <div class="card-head">
                <h3 class="card-title">Near-Expiry List</h3>

                <div class="expiry-filter">
                    @if ($custom)
                        <form method="GET" action="{{ route('alerts') }}" class="days-form">
                            <input type="number" name="days" min="1" max="365" value="{{ $days }}" class="input days-input">
                            <span>days</span>
                            <button type="submit" class="btn btn-small">Go</button>
                        </form>
                    @endif

                    <div class="segmented">
                        <a href="{{ route('alerts') }}?days=30" class="seg-btn {{ ($days == 30) ? 'active' : '' }}">30 days</a>
                        <a href="{{ route('alerts') }}?days=60" class="seg-btn {{ ($days == 60) ? 'active' : '' }}">60 days</a>
                        <a href="{{ route('alerts') }}?days=90" class="seg-btn {{ ($days == 90) ? 'active' : '' }}">90 days</a>
                        <a href="{{ route('alerts') }}?days={{ $custom ? $days : 120 }}" class="seg-btn {{ ($custom) ? 'active' : '' }}">Custom</a>
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>MEDICINE</th>
                        <th>BATCH NO.</th>
                        <th>EXPIRY DATE</th>
                        <th>DAYS LEFT</th>
                        <th>QTY</th>
                        <th>STATUS</th>
                    </tr>

                    @if (count($expiry_list) == 0)
                        <tr><td colspan="6" class="empty-row">No batches expire within {{ $days }} days</td></tr>
                    @endif

                    @foreach ($expiry_list as $row)
                        @php
// 30 days or less is red, the rest is amber
                        if ($row['days_left'] <= 30) {
                            $color = "status-red";
                            $status_text = "Within 30 days";
                        } elseif ($row['days_left'] <= 60) {
                            $color = "status-amber";
                            $status_text = "Within 60 days";
                        } elseif ($row['days_left'] <= 90) {
                            $color = "status-amber";
                            $status_text = "Within 90 days";
                        } else {
                            $color = "status-amber";
                            $status_text = "Within " . $days . " days";
                        }
@endphp
                        <tr>
                            <td class="cell-strong">{{ $row['medicine_name'] }}</td>
                            <td>{{ $row['batch_no'] }}</td>
                            <td>{{ date("M d, Y", strtotime($row['expiry_date'])) }}</td>
                            <td class="{{ $color }}">{{ $row['days_left'] . " " . ($row['days_left'] == 1 ? "day" : "days") }}</td>
                            <td>{{ number_format($row['quantity']) . " " . unit_text($row['unit'], $row['quantity']) }}</td>
                            <td class="{{ $color }}">{{ $status_text }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
@endsection
