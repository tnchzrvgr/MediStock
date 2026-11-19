@extends('layouts.app')

@section('content')
        @if (count($suppliers) == 0)
            <div class="flash flash-error">No suppliers found. Please run suppliers.sql in phpMyAdmin first.</div>
        @endif

        <div class="form-columns">

            <!-- stock-in form -->
            <form method="POST" action="{{ route('stock.in.save') }}" class="card form-card">
@csrf
                <h3 class="form-title">Stock-In Details</h3>

                <div class="field-row">
                    <div class="field">
                        <label>Supplier <span class="required">*</span></label>
                        <select name="supplier" class="input{{ error_class($field_errors, 'supplier') }}">
                            <option value="">Select supplier</option>
                            @foreach ($suppliers as $s)
                                <option value="{{ $s['supplier_name'] }}" {{ ($form['supplier'] == $s['supplier_name']) ? "selected" : '' }}>
                                    {{ $s['supplier_name'] }}
                                </option>
                            @endforeach
                        </select>
                        {!! show_error($field_errors, 'supplier') !!}
                    </div>

                    <div class="field">
                        <label>Medicine <span class="required">*</span></label>
                        <select name="medicine_id" id="medicine" class="input{{ error_class($field_errors, 'medicine_id') }}" onchange="updateSummary()">
                            <option value="0">Select medicine</option>
                            @foreach ($medicines as $m)
                                <option value="{{ $m['medicine_id'] }}"
                                        data-name="{{ $m['medicine_name'] }}"
                                        data-qty="{{ $m['quantity'] }}"
                                        data-min="{{ $m['reorder_level'] }}"
                                        data-max="{{ $m['max_stock'] }}"
                                        {{ ($form['medicine_id'] == $m['medicine_id']) ? "selected" : '' }}>
                                    {{ $m['medicine_name'] }}
                                </option>
                            @endforeach
                        </select>
                        {!! show_error($field_errors, 'medicine_id') !!}
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Quantity received <span class="required">*</span></label>
                        <input type="number" min="1" name="quantity" id="qty" class="input{{ error_class($field_errors, 'quantity') }}" value="{{ $form['quantity'] }}" oninput="updateSummary()">
                        {!! show_error($field_errors, 'quantity') !!}
                    </div>

                    <div class="field">
                        <label>Batch number <span class="required">*</span></label>
                        <input type="text" name="batch_no" id="batch" class="input{{ error_class($field_errors, 'batch_no') }}" value="{{ $form['batch_no'] }}" oninput="updateSummary()">
                        {!! show_error($field_errors, 'batch_no') !!}
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Expiration date <span class="required">*</span></label>
                        <input type="date" name="expiry_date" id="expiry" class="input{{ error_class($field_errors, 'expiry_date') }}" value="{{ $form['expiry_date'] }}" oninput="updateSummary()">
                        {!! show_error($field_errors, 'expiry_date') !!}
                    </div>

                    <div class="field">
                        <label>Date received <span class="required">*</span></label>
                        <input type="date" name="received" class="input{{ error_class($field_errors, 'received') }}" value="{{ $form['received'] }}">
                        {!! show_error($field_errors, 'received') !!}
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('inventory') }}" class="btn">Cancel</a>
                    <button type="submit" class="btn btn-primary">Record Stock-In</button>
                </div>
            </form>

            <!-- summary (changes while typing) -->
            <div class="side-col">
                <div class="card side-card">
                    <h3 class="card-title">Stock-In Summary</h3>

                    <div class="sum-row"><span>Medicine</span><strong id="sMedicine">—</strong></div>
                    <div class="sum-row"><span>Current stock</span><strong id="sCurrent">—</strong></div>
                    <div class="sum-row"><span>Quantity to add</span><strong id="sAdd">—</strong></div>
                    <div class="sum-row"><span>Batch</span><strong id="sBatch">—</strong></div>
                    <div class="sum-row"><span>Expiry</span><strong id="sExpiry">—</strong></div>

                    <div class="preview-box preview-normal" id="newBox">
                        <strong class="new-stock-text" id="sNew">New stock: —</strong>
                        <span class="pill pill-up" id="sStatus">Normal</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- recent stock-in history -->
        <div class="card table-card">
            <h3 class="card-title">Recent Stock-In History</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>DATE</th>
                        <th>MEDICINE</th>
                        <th>SUPPLIER</th>
                        <th>QTY</th>
                        <th>BATCH</th>
                        <th>EXPIRY</th>
                    </tr>

                    @if (count($history) == 0)
                        <tr><td colspan="6" class="empty-row">No stock-in records yet</td></tr>
                    @endif

                    @foreach ($history as $row)
                        <tr>
                            <td class="cell-strong">{{ date("M d, Y", strtotime($row['date_received'])) }}</td>
                            <td>{{ $row['medicine_name'] }}</td>
                            <td>{{ $row['supplier_name'] }}</td>
                            <td>{{ number_format($row['quantity']) }}</td>
                            <td>{{ $row['batch_no'] }}</td>
                            <td>
                                @php
if ($row['expiry_date'] == "") {
                                    echo "—";
                                } else {
                                    echo date("M Y", strtotime($row['expiry_date']));
                                }
@endphp
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
@endsection

@push('scripts')
<script>

var months = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

// updates the Stock-In Summary while the user is typing
function updateSummary() {
    var select = document.getElementById("medicine");
    var option = select.options[select.selectedIndex];
    var qty = parseInt(document.getElementById("qty").value);
    var batch = document.getElementById("batch").value;
    var expiry = document.getElementById("expiry").value;

    var hasMedicine = (select.value != "0");
    var current = 0;
    var min = 0;
    var max = 0;

    if (hasMedicine) {
        current = parseInt(option.getAttribute("data-qty"));
        min = parseInt(option.getAttribute("data-min"));
        max = parseInt(option.getAttribute("data-max"));
        document.getElementById("sMedicine").innerHTML = option.getAttribute("data-name");
        document.getElementById("sCurrent").innerHTML = current.toLocaleString() + " units";
    } else {
        document.getElementById("sMedicine").innerHTML = "—";
        document.getElementById("sCurrent").innerHTML = "—";
    }

    if (isNaN(qty) || qty < 1) {
        qty = 0;
        document.getElementById("sAdd").innerHTML = "—";
    } else {
        document.getElementById("sAdd").innerHTML = "+ " + qty.toLocaleString() + " units";
    }

    if (batch == "") {
        document.getElementById("sBatch").innerHTML = "—";
    } else {
        document.getElementById("sBatch").innerHTML = batch;
    }

    if (expiry == "") {
        document.getElementById("sExpiry").innerHTML = "—";
    } else {
        var p = expiry.split("-");
        document.getElementById("sExpiry").innerHTML = months[parseInt(p[1]) - 1] + " " + parseInt(p[2]) + ", " + p[0];
    }

    // new stock and its status
    var box = document.getElementById("newBox");
    var status = document.getElementById("sStatus");
    if (!hasMedicine) {
        document.getElementById("sNew").innerHTML = "New stock: —";
        return;
    }
    var newStock = current + qty;
    document.getElementById("sNew").innerHTML = "New stock: " + newStock.toLocaleString() + " units";

    if (newStock <= min) {
        status.innerHTML = "Low";
        status.className = "pill pill-danger";
        box.className = "preview-box preview-low";
    } else if (newStock > max) {
        status.innerHTML = "Overstock";
        status.className = "pill pill-amber";
        box.className = "preview-box preview-over";
    } else {
        status.innerHTML = "Normal";
        status.className = "pill pill-up";
        box.className = "preview-box preview-normal";
    }
}

updateSummary();
</script>
@endpush
