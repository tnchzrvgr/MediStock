@extends('layouts.app')

@section('content')
        <!-- 4 cards -->
        <div class="kpi-row">
            <div class="card kpi-card">
                <span class="kpi-label">Total Medicines</span>
                <span class="kpi-value">{{ number_format($kpi['total_skus']) }}</span>
                <span class="pill pill-up">▲ {{ number_format($kpi['added_this_month']) }} added this month</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Well Stocked</span>
                <span class="kpi-value">{{ number_format($kpi['in_stock']) }}</span>
                <span class="pill pill-up">Enough on the shelf</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Running Low</span>
                <span class="kpi-value">{{ medicine_count_text($kpi['low_stock']) }}</span>
                <span class="pill pill-danger">Time to reorder</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Out of Stock</span>
                <span class="kpi-value">{{ medicine_count_text($kpi['out_of_stock']) }}</span>
                <span class="pill pill-danger">Restock now</span>
            </div>
        </div>

        @if (is_admin() && $pending_count > 0)
            <div class="flash flash-info">{{ $pending_count }} stock adjustment {{ $pending_count == 1 ? 'request is' : 'requests are' }} waiting for your approval. <a href="{{ route('stock.adjust') }}">Review</a></div>
        @endif

        <!-- filter buttons and action buttons -->
        <div class="toolbar">
            <div class="filter-group">
                <a href="{{ route('inventory') }}?filter=all{{ $search_link }}" class="filter-btn {{ ($filter == 'all') ? 'active' : '' }}">All</a>
                <a href="{{ route('inventory') }}?filter=low{{ $search_link }}" class="filter-btn {{ ($filter == 'low') ? 'active' : '' }}">Low Stock</a>
                <a href="{{ route('inventory') }}?filter=expiring{{ $search_link }}" class="filter-btn {{ ($filter == 'expiring') ? 'active' : '' }}">Expiring Soon</a>
                <a href="{{ route('inventory') }}?filter=out{{ $search_link }}" class="filter-btn {{ ($filter == 'out') ? 'active' : '' }}">Out of Stock</a>
            </div>

            <div class="action-group">
                <a href="{{ route('stock.in') }}" class="btn">Stock-In</a>
                <a href="{{ route('stock.adjust') }}" class="btn">{{ is_admin() ? 'Adjust Stock' : 'Request Adjustment' }}</a>
                @if (is_admin())
                    <a href="{{ route('medicines.form') }}" class="btn btn-primary">+ Add Medicine</a>
                @endif
            </div>
        </div>

        <!-- medicine table -->
        <div class="card table-card">
            <h3 class="card-title">Medicine Inventory</h3>
            <div class="table-wrap">
                <table class="data-table inventory-table">
                    <tr>
                        <th>MEDICINE</th>
                        <th>CATEGORY</th>
                        <th>BATCH NO.</th>
                        <th>STOCK QTY</th>
                        <th>UNIT PRICE</th>
                        <th>EXPIRY DATE</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>

                    @if (count($medicines) == 0)
                        <tr><td colspan="8" class="empty-row">No medicines found</td></tr>
                    @endif

                    @foreach ($medicines as $row)
                        @php
// color of the status text
                        if ($row['status'] == "In Stock") {
                            $status_class = "status-green";
                        } elseif ($row['status'] == "Expiring Soon") {
                            $status_class = "status-amber";
                        } elseif ($row['status'] == "Overstock") {
                            $status_class = "status-blue";
                        } else {
                            $status_class = "status-red";
                        }

                        // no expiry date shown when the medicine is out of stock
                        if ($row['quantity'] == 0 || $row['expiry_date'] == "") {
                            $expiry_text = "—";
                        } else {
                            $expiry_text = date("M Y", strtotime($row['expiry_date']));
                        }
@endphp
                        <tr>
                            <td class="cell-strong">{{ $row['medicine_name'] }}</td>
                            <td>{{ $row['category_name'] }}</td>
                            <td>{{ $row['batch_no'] }}</td>
                            <td>{{ number_format($row['quantity']) . " " . $row['unit'] }}</td>
                            <td>₱ {{ number_format($row['unit_price'], 2) }}</td>
                            <td>{{ $expiry_text }}</td>
                            <td class="{{ $status_class }}">{{ $row['status'] }}</td>
                            <td class="cell-actions">
                                <a href="{{ route('medicines.show', ['id' => $row['medicine_id']]) }}">View</a>
                                @if (is_admin())
                                    · <a href="{{ route('medicines.form') }}?id={{ $row['medicine_id'] }}">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
@endsection
