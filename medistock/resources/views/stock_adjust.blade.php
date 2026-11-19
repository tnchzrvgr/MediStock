@extends('layouts.app')

@section('content')
        <div class="flash flash-info">Some medicines may already be expired. <a href="{{ route('stock.expired') }}">{{ is_admin() ? 'Remove expired stock' : 'Request removal of expired stock' }}</a></div>

        <!-- requests waiting for the owner -->
        @if (count($pending) > 0)
        <div class="card table-card">
            <h3 class="card-title">Waiting for Approval ({{ count($pending) }})</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>REQUESTED</th>
                        <th>MEDICINE</th>
                        <th>CHANGE</th>
                        <th>REASON</th>
                        <th>NOTES</th>
                        <th>BY</th>
                        <th>ACTIONS</th>
                    </tr>

                    @foreach ($pending as $row)
                        @php
$amount = abs($row['change_qty']);
                        $unit = unit_text($row['unit'], $amount);
@endphp
                        <tr>
                            <td class="cell-strong">{{ date("M d, g:i A", strtotime($row['adjust_time'])) }}</td>
                            <td>{{ $row['medicine_name'] }}</td>
                            @if ($row['change_qty'] < 0)
                                <td class="status-red">− {{ $amount . " " . $unit }}</td>
                            @else
                                <td class="status-green">+ {{ $amount . " " . $unit }}</td>
                            @endif
                            <td>{{ $row['reason'] }}</td>
                            <td>{{ $row['notes'] != '' ? $row['notes'] : '—' }}</td>
                            <td>{{ $row['staff_name'] }}</td>
                            <td class="cell-actions">
                                @if (is_admin())
                                    <form method="POST" action="{{ route('stock.adjust.decide') }}" class="inline-form">
                                        @csrf
                                        <input type="hidden" name="adjust_id" value="{{ $row['adjust_id'] }}">
                                        <button type="submit" name="decision" value="approve" class="btn btn-primary btn-small">Approve</button>
                                        <button type="submit" name="decision" value="reject" class="btn btn-small" onclick="return confirm('Reject this request?');">Reject</button>
                                    </form>
                                @else
                                    <span class="pill pill-amber">Waiting for the owner</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
        @endif

        <div class="form-columns">

            <!-- adjustment form -->
            <form method="POST" action="{{ route('stock.adjust.save') }}" class="card form-card">
@csrf
                <h3 class="form-title">{{ is_admin() ? 'Adjust Stock' : 'Request an Adjustment' }}</h3>

                <div class="field-row">
                    <div class="field">
                        <label>Medicine <span class="required">*</span></label>
                        <select name="medicine_id" id="medicine" class="input{{ error_class($field_errors, 'medicine_id') }}" onchange="updateNumbers()">
                            <option value="0">Select medicine</option>
                            @foreach ($medicines as $m)
                                <option value="{{ $m['medicine_id'] }}" data-qty="{{ $m['quantity'] }}" {{ ($form['medicine_id'] == $m['medicine_id']) ? "selected" : '' }}>
                                    {{ $m['medicine_name'] }}
                                </option>
                            @endforeach
                        </select>
                        {!! show_error($field_errors, 'medicine_id') !!}
                    </div>

                    <div class="field">
                        <label>Current quantity</label>
                        <input type="text" id="current" class="input" value="" readonly>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label id="qtyLabel">Quantity to remove <span class="required">*</span></label>
                        <input type="number" name="quantity" id="qty" class="input{{ error_class($field_errors, 'quantity') }}" value="{{ $form['quantity'] }}" oninput="updateNumbers()">
                        {!! show_error($field_errors, 'quantity') !!}
                        <span class="field-hint" id="qtyHint"></span>
                    </div>

                    <div class="field">
                        <label>New quantity</label>
                        <input type="text" id="newQty" class="input" value="" readonly>
                    </div>
                </div>

                <div class="field">
                    <label>Reason <span class="required">* required</span></label>
                    <div class="reason-options">
                        @foreach ($reasons as $r)
                            <label class="reason-pill">
                                <input type="radio" name="reason" value="{{ $r }}" {{ ($form['reason'] == $r) ? "checked" : '' }} onclick="updateNumbers()">
                                <span>{{ $r }}</span>
                            </label>
                        @endforeach
                    </div>
                    {!! show_error($field_errors, 'reason') !!}
                </div>

                <div class="field">
                    <label>Notes</label>
                    <textarea name="notes" class="input textarea{{ error_class($field_errors, 'notes') }}">{{ $form['notes'] }}</textarea>
                    {!! show_error($field_errors, 'notes') !!}
                </div>

                <div class="form-actions">
                    <a href="{{ route('inventory') }}" class="btn">Cancel</a>
                    <button type="submit" class="btn btn-primary">{{ is_admin() ? 'Save Adjustment' : 'Send Request' }}</button>
                </div>
            </form>

            <!-- why a reason is required -->
            <div class="side-col">
                <div class="card side-card">
                    <h3 class="card-title">Why a reason is required</h3>
                    <p class="side-text">Every adjustment is saved to the log with the staff name, date, reason and old/new quantity so stock changes can always be traced.</p>
                    @if (!is_admin())
                        <p class="side-text">Your request goes to the owner. The stock only changes after the owner approves it.</p>
                    @endif
                    <div class="amber-note">Adjustments cannot be saved without a reason.</div>
                </div>
            </div>

        </div>

        <!-- adjustment log -->
        <div class="card table-card">
            <h3 class="card-title">Adjustment Log</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>DATE &amp; TIME</th>
                        <th>MEDICINE</th>
                        <th>CHANGE</th>
                        <th>REASON</th>
                        <th>BY</th>
                        <th>STATUS</th>
                    </tr>

                    @if (count($log) == 0)
                        <tr><td colspan="6" class="empty-row">No adjustments yet</td></tr>
                    @endif

                    @foreach ($log as $row)
                        @php
$amount = abs($row['change_qty']);
                        $unit = unit_text($row['unit'], $amount);
@endphp
                        <tr>
                            <td class="cell-strong">{{ date("M d, g:i A", strtotime($row['adjust_time'])) }}</td>
                            <td>{{ $row['medicine_name'] }}</td>
                            @if ($row['change_qty'] < 0)
                                <td class="status-red">− {{ $amount . " " . $unit }}</td>
                            @else
                                <td class="status-green">+ {{ $amount . " " . $unit }}</td>
                            @endif
                            <td>{{ $row['reason'] }}</td>
                            <td>{{ $row['staff_name'] }}</td>
                            @if ($row['status'] == 'Rejected')
                                <td class="status-red">Rejected by {{ $row['approver_name'] }}</td>
                            @elseif ($row['approver_name'] != '—' && $row['approver_name'] != $row['staff_name'])
                                <td class="status-green">Approved by {{ $row['approver_name'] }}</td>
                            @else
                                <td class="status-green">Approved</td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
@endsection

@push('scripts')
<script>

// shows the current quantity and works out the new quantity while the user is typing
function updateNumbers() {
    var select = document.getElementById("medicine");
    var option = select.options[select.selectedIndex];
    var qty = parseInt(document.getElementById("qty").value);
    var checked = document.querySelector("input[name=reason]:checked");

    // Count correction can add (+) or remove (-), Damaged / Expired only remove
    var reason = "";
    if (checked) {
        reason = checked.value;
    }
    var label = document.getElementById("qtyLabel");
    var hint = document.getElementById("qtyHint");
    if (reason == "Count correction") {
        label.innerHTML = 'Quantity change <span class="required">*</span>';
        hint.innerHTML = "Use + to add stock or − (minus) to remove stock.";
    } else {
        label.innerHTML = 'Quantity to remove <span class="required">*</span>';
        hint.innerHTML = "";
    }

    if (select.value == "0") {
        document.getElementById("current").value = "";
        document.getElementById("newQty").value = "";
        return;
    }

    var current = parseInt(option.getAttribute("data-qty"));
    document.getElementById("current").value = current;

    if (isNaN(qty)) {
        document.getElementById("newQty").value = current;
        return;
    }

    var change = -qty;
    if (reason == "Count correction") {
        change = qty;
    }
    document.getElementById("newQty").value = current + change;
}

updateNumbers();
</script>
@endpush
