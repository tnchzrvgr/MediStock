@extends('layouts.app')

@section('content')
        <div class="receipt-layout">

            <!-- the receipt -->
            <div class="card receipt-card" id="receipt">
                <div class="receipt-head">
                    <h2>{{ $shop['pharmacy_name'] }}</h2>
                    @if ($shop['address'] != "")
                        <p>{{ $shop['address'] }}</p>
                    @endif
                    @if ($shop['contact_number'] != "")
                        <p>Tel: {{ $shop['contact_number'] }}</p>
                    @endif
                </div>

                @if ($sale['status'] != 'Completed')
                    <p class="receipt-status">{{ strtoupper($sale['status']) }}</p>
                @endif

                <div class="receipt-lines">
                    <div class="receipt-line"><span>Receipt No.</span><strong>{{ $sale['order_no'] }}</strong></div>
                    <div class="receipt-line"><span>Date</span><strong>{{ date("M j, Y · g:i A", strtotime($sale['sale_date'])) }}</strong></div>
                    <div class="receipt-line"><span>Cashier</span><strong>{{ $sale['cashier_name'] }}</strong></div>
                </div>

                <div class="receipt-items">
                    @if (count($items) == 0)
                        <p class="receipt-empty">No items were saved for this sale.</p>
                    @endif

                    @foreach ($items as $item)
                        <div class="receipt-item">
                            <div>
                                <strong>{{ $item['medicine_name'] }}</strong>
                                <span>{{ $item['quantity'] }} × ₱ {{ number_format($item['unit_price'], 2) }}</span>
                            </div>
                            <strong>₱ {{ number_format($item['line_total'], 2) }}</strong>
                        </div>
                    @endforeach
                </div>

                <div class="receipt-lines">
                    @if (count($items) > 0)
                        <div class="receipt-line"><span>Subtotal</span><strong>₱ {{ number_format($subtotal, 2) }}</strong></div>
                    @endif
                    <div class="receipt-line"><span>{{ $sale['payment_method'] }}</span><strong>₱ {{ number_format($received, 2) }}</strong></div>
                    @if ($sale['payment_method'] == 'Cash')
                        <div class="receipt-line"><span>Change</span><strong>₱ {{ number_format($change, 2) }}</strong></div>
                    @endif
                    <div class="receipt-total"><span>TOTAL</span><strong>₱ {{ number_format($total, 2) }}</strong></div>
                </div>

                <p class="receipt-foot">{{ $shop['receipt_footer'] }}</p>
            </div>

            <!-- right side cards (not printed) -->
            <div class="side-col receipt-side">

                <div class="card side-card">
                    <h3 class="card-title">Receipt Actions</h3>
                    <button type="button" class="btn btn-primary receipt-btn" onclick="window.print()">Print Receipt</button>
                    <button type="button" class="btn receipt-btn" onclick="window.print()" title="In the print window, choose Save as PDF">Download PDF</button>
                    <a href="{{ route('sales.new') }}" class="btn receipt-btn">New Sale</a>
                </div>

                @if ($just_completed && count($items) > 0)
                    <div class="card side-card">
                        <h3 class="card-title">Stock Updated Automatically</h3>
                        @foreach ($items as $item)
                            <div class="stock-row">
                                <span>{{ $item['medicine_name'] }}</span>
                                <strong>{{ number_format($item['stock_now'] + $item['quantity']) }} → {{ number_format($item['stock_now']) }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        </div>
@endsection

@push('scripts')
@if ($just_completed && $shop['print_receipt'] == 1)
<script>
// the Settings page says: open the print window after every completed sale
window.onload = function () {
    window.print();
};
</script>
@endif
@endpush
