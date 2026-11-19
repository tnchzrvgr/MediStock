@extends('layouts.app')

@section('content')
        <div class="form-columns">

            <!-- left side -->
            <div class="detail-main">

                <div class="card">
                    <h3 class="card-title">Supplier Information</h3>
                    <div class="info-grid">
                        <div class="info-col">
                            <div class="info-row"><span>Contact person</span><strong>{{ $supplier['contact_person'] }}</strong></div>
                            <div class="info-row"><span>Phone</span><strong>{{ $supplier['phone'] != '' ? $supplier['phone'] : '—' }}</strong></div>
                            <div class="info-row"><span>Email</span><strong>{{ $supplier['email'] != '' ? $supplier['email'] : '—' }}</strong></div>
                            <div class="info-row"><span>Address</span><strong>{{ $supplier['address'] != '' ? $supplier['address'] : '—' }}</strong></div>
                        </div>

                        <div class="info-col">
                            <div class="info-row"><span>Payment terms</span><strong>{{ $supplier['payment_terms'] }}</strong></div>
                            <div class="info-row"><span>Delivery lead time</span><strong>{{ $supplier['lead_time_days'] }} {{ $supplier['lead_time_days'] == 1 ? 'day' : 'days' }}</strong></div>
                            <div class="info-row"><span>Date added</span><strong>{{ date("M d, Y", strtotime($supplier['date_added'])) }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h3 class="card-title">Products Supplied</h3>
                    <div class="chip-row">
                        @foreach ($products as $product)
                            <span class="chip-static">{{ $product }}</span>
                        @endforeach
                    </div>

                    @if ($supplier['notes'] != '')
                        <h3 class="card-title notes-title">Notes</h3>
                        <p class="side-text">{{ $supplier['notes'] }}</p>
                    @endif
                </div>

                <div class="card table-card">
                    <h3 class="card-title">Recent Deliveries (Stock-In)</h3>
                    <div class="table-wrap">
                        <table class="data-table">
                            <tr>
                                <th>DATE RECEIVED</th>
                                <th>MEDICINE</th>
                                <th>BATCH NO.</th>
                                <th>QTY</th>
                            </tr>

                            @if (count($deliveries) == 0)
                                <tr><td colspan="4" class="empty-row">No deliveries recorded yet</td></tr>
                            @endif

                            @foreach ($deliveries as $d)
                                <tr>
                                    <td class="cell-strong">{{ date("M d, Y", strtotime($d['date_received'])) }}</td>
                                    <td>{{ $d['medicine_name'] }}</td>
                                    <td>{{ $d['batch_no'] }}</td>
                                    <td class="status-green">+ {{ number_format($d['quantity']) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>

            <!-- right side -->
            <div class="side-col">

                <div class="card side-card">
                    <h3 class="card-title">Supplier Status</h3>
                    <span class="stock-big stock-medium {{ status_color($supplier['status']) }}">{{ $supplier['status'] }}</span>
                    <div class="info-row"><span>Products supplied</span><strong>{{ count($products) }}</strong></div>
                    <div class="info-row"><span>Last delivery</span><strong>{{ $last_delivery }}</strong></div>
                </div>

                <div class="card side-card">
                    <h3 class="card-title">Actions</h3>
                    <a href="{{ route('suppliers.form', ['id' => $supplier['supplier_id']]) }}" class="btn btn-primary receipt-btn">Edit Supplier</a>
                    <a href="{{ route('suppliers') }}" class="btn receipt-btn">Back to Suppliers</a>
                </div>
            </div>
        </div>
@endsection
