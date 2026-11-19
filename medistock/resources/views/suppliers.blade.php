@extends('layouts.app')

@section('content')
        <!-- 4 cards -->
        <div class="kpi-row">
            <div class="card kpi-card">
                <span class="kpi-label">Total Suppliers</span>
                <span class="kpi-value">{{ number_format($kpi['total_suppliers']) }}</span>
                <span class="pill pill-up">▲ {{ number_format($kpi['new_this_month']) }} new this month</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Active</span>
                <span class="kpi-value">{{ number_format($kpi['active_suppliers']) }}</span>
                <span class="pill pill-up">Ready for ordering</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">On Hold</span>
                <span class="kpi-value">{{ number_format($kpi['on_hold_suppliers']) }}</span>
                <span class="pill pill-amber">Orders paused</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Inactive</span>
                <span class="kpi-value">{{ number_format($kpi['inactive_suppliers']) }}</span>
                <span class="pill pill-blue">No longer used</span>
            </div>
        </div>

        <!-- supplier directory -->
        <div class="card table-card">
            <div class="card-head">
                <h3 class="card-title">Supplier Directory</h3>
                <a href="{{ route('suppliers.form') }}" class="btn btn-primary">+ Add Supplier</a>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>SUPPLIER</th>
                        <th>CONTACT PERSON</th>
                        <th>PRODUCTS SUPPLIED</th>
                        <th>DATE ADDED</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>

                    @if (count($suppliers) == 0)
                        <tr><td colspan="6" class="empty-row">No suppliers yet</td></tr>
                    @endif

                    @foreach ($suppliers as $row)
                        <tr>
                            <td class="cell-strong">{{ $row['supplier_name'] }}</td>
                            <td>{{ $row['contact_person'] }}</td>
                            <td>{{ $row['products_supplied'] }}</td>
                            <td>{{ date("M d, Y", strtotime($row['date_added'])) }}</td>
                            <td class="{{ status_color($row['status']) }}">{{ $row['status'] }}</td>
                            <td class="cell-actions">
                                <a href="{{ route('suppliers.show', ['id' => $row['supplier_id']]) }}">View</a> ·
                                <a href="{{ route('suppliers.form') }}?id={{ $row['supplier_id'] }}">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
@endsection
