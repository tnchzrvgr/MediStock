@extends('layouts.app')

@section('content')
        <div class="form-columns">

            <!-- left side: add item + cart -->
            <div class="sale-left">

                <div class="card sale-card">
                    <h3 class="card-title">Add Item</h3>

                    <div class="add-row">
                        <form method="GET" action="{{ route('sales.new') }}" class="field add-search">
                            <label>Search medicine</label>
                            <input type="text" name="q" class="input" placeholder="Type a medicine name and press Enter" value="{{ $q }}">
                        </form>

                        <form method="POST" action="{{ route('sales.new') }}" class="add-form">
@csrf
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="medicine_id" value="{{ $pick }}">
                            <div class="field add-qty">
                                <label>Quantity</label>
                                <input type="number" name="quantity" class="input" value="1" min="1">
                            </div>
                            <button type="submit" class="btn btn-primary add-btn">Add to Cart</button>
                        </form>
                    </div>

                    @if ($q != "")
                        <div class="results">
                            @if (count($results) == 0)
                                <div class="result-row result-empty">No medicine found for "{{ $q }}"</div>
                            @endif

                            @foreach ($results as $r)
                                <a href="{{ route('sales.new') }}?q={{ urlencode($q) }}&pick={{ $r['medicine_id'] }}" class="result-row {{ ($pick == $r['medicine_id']) ? 'selected' : '' }}">
                                    <span class="result-name">{{ $r['medicine_name'] }}</span>
                                    <span class="result-price">₱ {{ number_format($r['unit_price'], 2) }}</span>
                                    <span class="result-stock">Stock: {{ number_format($r['quantity']) }}</span>
                                    @if ($r['is_expired'] == 1)
                                        <span class="pill pill-down">Expired</span>
                                    @else
                                        <span class="pill {{ badge_class($r['status']) }}">{{ badge_text($r['status']) }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                        <p class="field-hint">Click a medicine in the list, then press Add to Cart.</p>
                    @endif
                </div>

                <div class="card sale-card">
                    <div class="cart-head">
                        <h3 class="card-title">Cart ({{ count($cart_rows) }} items)</h3>
                        <span class="card-note">Cashier: {{ session('full_name') }}</span>
                    </div>

                    <div class="table-wrap">
                        <table class="cart-table">
                            <tr>
                                <th>ITEM</th>
                                <th>QTY</th>
                                <th>UNIT PRICE</th>
                                <th>SUBTOTAL</th>
                                <th></th>
                            </tr>

                            @if (count($cart_rows) == 0)
                                <tr><td colspan="5" class="empty-row">The cart is empty. Search a medicine above to add it.</td></tr>
                            @endif

                            @foreach ($cart_rows as $row)
                                <tr class="{{ ($row['too_many']) ? 'cart-problem' : '' }}">
                                    <td class="cell-semibold">{{ $row['name'] }}</td>
                                    <td class="{{ ($row['too_many']) ? 'qty-red' : '' }}">{{ $row['qty'] }}</td>
                                    <td>₱ {{ number_format($row['price'], 2) }}</td>
                                    <td class="cell-semibold">₱ {{ number_format($row['subtotal'], 2) }}</td>
                                    <td>
                                        <form method="POST" action="{{ route('sales.new') }}">
@csrf
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="medicine_id" value="{{ $row['id'] }}">
                                            <button type="submit" class="remove-btn" title="Remove">✕</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>

                    @foreach ($problems as $problem)
                        <div class="validation-box">{{ $problem }}</div>
                    @endforeach
                </div>

            </div>

            <!-- right side: order summary -->
            <div class="side-col">
                <form method="POST" action="{{ route('sales.new') }}" class="card side-card">
@csrf
                    <input type="hidden" name="action" value="complete">

                    <h3 class="card-title">Order Summary</h3>

                    <div class="sum-row">
                        <span>Subtotal</span>
                        <strong>₱ {{ number_format($total, 2) }}</strong>
                    </div>
                    <div class="sum-row">
                        <span>Items</span>
                        <strong>{{ count($cart_rows) }} lines · {{ $total_units }} units</strong>
                    </div>

                    <div class="total-box">
                        <span>Total</span>
                        <strong>₱ {{ number_format($total, 2) }}</strong>
                    </div>

                    <div class="field">
                        <label class="small-label">Payment method</label>
                        <div class="segmented pay-seg">
                            <label class="pay-option"><input type="radio" name="payment_method" value="Cash" checked onclick="payChanged()"><span>Cash</span></label>
                            <label class="pay-option"><input type="radio" name="payment_method" value="GCash" onclick="payChanged()"><span>GCash</span></label>
                        </div>
                    </div>

                    <div class="field" id="receivedField">
                        <label>Amount received</label>
                        <input type="number" step="0.01" min="0" name="amount_received" id="received" class="input" placeholder="0.00" oninput="showChange()">
                    </div>

                    <div class="sum-row">
                        <span>Change</span>
                        <strong class="change-green" id="changeText">₱ 0.00</strong>
                    </div>

                    <button type="submit" class="btn btn-primary complete-btn" {{ (!$can_complete) ? "disabled" : '' }}>Complete Sale</button>

                    <p class="field-hint">Stock is deducted automatically once the sale is completed.</p>
                </form>
            </div>

        </div>
@endsection

@push('scripts')
<script>

// the total of the cart (calculated by PHP above)
var total = {!! $total !!};

// shows the change when the amount received is typed
function showChange() {
    var received = parseFloat(document.getElementById("received").value);
    var change = 0;
    if (!isNaN(received) && received > total) {
        change = received - total;
    }
    document.getElementById("changeText").innerHTML = "₱ " + change.toFixed(2);
}

// only cash needs the amount received
function payChanged() {
    var pay = document.querySelector("input[name=payment_method]:checked").value;
    var box = document.getElementById("receivedField");
    if (pay == "Cash") {
        box.style.display = "flex";
    } else {
        box.style.display = "none";
        document.getElementById("changeText").innerHTML = "₱ 0.00";
    }
}
</script>
@endpush
