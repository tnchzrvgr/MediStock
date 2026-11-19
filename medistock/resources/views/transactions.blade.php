@extends('layouts.app')

@section('content')
        <!-- search -->
        <form method="GET" action="{{ route('transactions') }}" class="card filter-card">
            <div class="field filter-item">
                <label>Item</label>
                <input type="text" name="item" class="input" placeholder="Search by medicine name..." value="{{ $item }}">
            </div>

            <div class="field filter-range">
                <label>Date range</label>
                <select name="range" class="input">
                    @foreach ($ranges as $key => $label)
                        <option value="{{ $key }}" {{ ($range == $key) ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field filter-small">
                <label>Staff</label>
                <select name="staff" class="input">
                    <option value="0">All staff</option>
                    @foreach ($staff_list as $s)
                        <option value="{{ $s['user_id'] }}" {{ ($staff == $s['user_id']) ? 'selected' : '' }}>{{ $s['full_name'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field filter-small">
                <label>Status</label>
                <select name="status" class="input">
                    <option value="">All</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st }}" {{ ($status == $st) ? 'selected' : '' }}>{{ $st }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="btn btn-primary filter-btn">Search</button>
        </form>

        <!-- sales table -->
        <div class="card table-card">
            <div class="card-head">
                <h3 class="card-title">Sales Transactions</h3>
                <span class="pill pill-up">Admin only: Void / Return is logged</span>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>ORDER ID</th>
                        <th>DATE &amp; TIME</th>
                        <th>ITEMS</th>
                        <th>STAFF</th>
                        <th>PAYMENT</th>
                        <th>TOTAL</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>

                    @if (count($sales) == 0)
                        <tr><td colspan="8" class="empty-row">No transactions found</td></tr>
                    @endif

                    @foreach ($sales as $row)
                        <tr>
                            <td class="cell-strong">{{ $row['order_no'] }}</td>
                            <td>{{ date("M j, g:i A", strtotime($row['sale_date'])) }}</td>
                            <td>{{ items_text($row['item_count']) }}</td>
                            <td>{{ $row['staff_name'] }}</td>
                            <td>{{ $row['payment_method'] }}</td>
                            <td class="cell-semibold">₱ {{ number_format($row['total_amount'], 2) }}</td>
                            <td class="{{ sale_status_color($row['status']) }}">{{ $row['status'] }}</td>
                            <td class="cell-actions">
                                <a href="{{ route('receipt', ['id' => $row['sale_id']]) }}">View</a>
                                @if ($row['status'] == 'Completed' && session('role') == 'Admin')
                                    · <a href="{{ route('transactions', ['modal' => $row['sale_id'], 'mode' => 'void']) }}">Void</a>
                                    / <a href="{{ route('transactions', ['modal' => $row['sale_id'], 'mode' => 'return']) }}">Return</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>

        @if ($modal != null)
        <!-- Void / Return window -->
        <div class="modal-back">
            <form method="POST" action="{{ route('transactions.change') }}" class="modal-box">
                @csrf
                <input type="hidden" name="sale_id" value="{{ $modal['sale_id'] }}">

                <h3 class="modal-title">Void or Return Sale {{ $modal['order_no'] }}</h3>
                <p class="modal-sub">{{ $modal['cashier_name'] }} · {{ date("M j, Y g:i A", strtotime($modal['sale_date'])) }} · ₱ {{ number_format($modal['total_amount'], 2) }}</p>

                <label class="mode-card">
                    <input type="radio" name="mode" value="void" {{ $mode == 'void' ? 'checked' : '' }} onclick="modeChanged()">
                    <span class="mode-dot"></span>
                    <span class="mode-text"><strong>Void entire sale</strong><em>Cancel the sale and restore all items to stock.</em></span>
                </label>

                <label class="mode-card">
                    <input type="radio" name="mode" value="return" {{ $mode == 'return' ? 'checked' : '' }} onclick="modeChanged()">
                    <span class="mode-dot"></span>
                    <span class="mode-text"><strong>Return items</strong><em>Select items being returned; stock is restored for those items.</em></span>
                </label>

                <!-- the items to return (only for "Return items") -->
                <div id="returnItems" class="return-items" style="display: {{ $mode == 'return' ? 'block' : 'none' }}">
                    @if (count($modal_items) == 0)
                        <p class="modal-sub">No items were saved for this sale.</p>
                    @endif
                    @foreach ($modal_items as $it)
                        <div class="return-row">
                            <span>{{ $it['medicine_name'] }}<em>sold {{ $it['quantity'] }} × ₱ {{ number_format($it['unit_price'], 2) }}</em></span>
                            <input type="text" name="return_qty[{{ $it['medicine_id'] }}]" class="input qty-input" placeholder="0">
                        </div>
                    @endforeach
                </div>

                <div class="field">
                    <label>Reason <span class="required">*</span></label>
                    <select name="reason" class="input">
                        @foreach ($reasons as $r)
                            <option value="{{ $r }}">{{ $r }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label>Admin password <span class="required">*</span></label>
                    <input type="password" name="admin_password" class="input" autofocus>
                </div>

                <p class="amber-note">This action is logged in the activity log with your name, date and reason.</p>

                <div class="form-actions modal-actions">
                    <a href="{{ route('transactions') }}" class="btn">Cancel</a>
                    <button type="submit" class="btn btn-red" id="confirmBtn">{{ $mode == 'return' ? 'Confirm Return' : 'Confirm Void' }}</button>
                </div>
            </form>
        </div>
        @endif
@endsection

@push('scripts')
<script>
// shows the items list and the button text for the chosen option
function modeChanged() {
    var mode = document.querySelector("input[name=mode]:checked").value;
    document.getElementById("returnItems").style.display = (mode == "return") ? "block" : "none";
    document.getElementById("confirmBtn").innerHTML = (mode == "return") ? "Confirm Return" : "Confirm Void";
}
</script>
@endpush
