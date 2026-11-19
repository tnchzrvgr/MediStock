@extends('layouts.app')

@section('content')
        <div class="form-columns">

            <!-- left side -->
            <div class="detail-main">

                <div class="card">
                    <h3 class="card-title">Medicine Information</h3>
                    <div class="info-grid">
                        <div class="info-col">
                            <div class="info-row"><span>Generic name</span><strong>{{ $medicine['generic_name'] != '' ? $medicine['generic_name'] : '—' }}</strong></div>
                            <div class="info-row"><span>Category</span><strong>{{ $medicine['category_name'] }}</strong></div>
                            <div class="info-row"><span>Unit</span><strong>{{ $medicine['unit'] }}</strong></div>
                            <div class="info-row"><span>Unit price</span><strong>₱ {{ number_format($medicine['unit_price'], 2) }}</strong></div>
                        </div>

                        <div class="info-col">
                            <div class="info-row"><span>Quantity on hand</span><strong>{{ number_format($medicine['quantity']) }} units</strong></div>
                            <div class="info-row"><span>Minimum level</span><strong>{{ number_format($medicine['reorder_level']) }}</strong></div>
                            <div class="info-row"><span>Maximum level</span><strong>{{ number_format($medicine['max_stock']) }}</strong></div>
                            <div class="info-row"><span>Last stock-in</span><strong>{{ $medicine['last_stock_in'] != '' ? date("M d, Y", strtotime($medicine['last_stock_in'])) : '—' }}</strong></div>
                        </div>
                    </div>
                </div>

                <div class="card table-card">
                    <h3 class="card-title">Batches &amp; Expiry</h3>
                    <div class="table-wrap">
                        <table class="data-table">
                            <tr>
                                <th>BATCH NO.</th>
                                <th>DATE RECEIVED</th>
                                <th>EXPIRY DATE</th>
                                <th>QTY</th>
                                <th>STATUS</th>
                            </tr>

                            @if (count($batches) == 0)
                                <tr><td colspan="5" class="empty-row">No batches recorded yet</td></tr>
                            @endif

                            @foreach ($batches as $b)
                                <tr>
                                    <td class="cell-strong">{{ $b['batch_no'] }}</td>
                                    <td>{{ $b['received'] }}</td>
                                    <td>{{ $b['expiry'] }}</td>
                                    <td>{{ number_format($b['remaining']) }}</td>
                                    <td class="{{ $b['color'] }}">{{ $b['text'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                    <p class="field-hint">Qty is the stock left in each batch. The oldest stock is counted as sold first.</p>
                </div>

                <div class="card table-card">
                    <h3 class="card-title">Stock Movement History</h3>
                    <div class="table-wrap">
                        <table class="data-table">
                            <tr>
                                <th>DATE</th>
                                <th>TYPE</th>
                                <th>QTY</th>
                                <th>BY</th>
                            </tr>

                            @if (count($movements) == 0)
                                <tr><td colspan="4" class="empty-row">No stock movements yet</td></tr>
                            @endif

                            @foreach ($movements as $m)
                                <tr>
                                    <td class="cell-strong">{{ $m['when'] }}</td>
                                    <td>{{ $m['type'] }}</td>
                                    @if ($m['qty'] >= 0)
                                        <td class="status-green">+ {{ number_format($m['qty']) }}</td>
                                    @else
                                        <td class="status-red">− {{ number_format(abs($m['qty'])) }}</td>
                                    @endif
                                    <td>{{ $m['by'] }}</td>
                                </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
            </div>

            <!-- right side -->
            <div class="side-col">

                <div class="card side-card">
                    <h3 class="card-title">Stock Status</h3>
                    <span class="stock-big">{{ number_format($medicine['quantity']) }} units</span>
                    <span class="pill {{ $status_class }}">{{ $status_text }}</span>
                </div>

                <div class="card side-card">
                    <h3 class="card-title">Actions</h3>
                    @if (is_admin())
                        <a href="{{ route('medicines.form', ['id' => $medicine['medicine_id']]) }}" class="btn btn-primary receipt-btn">Edit Medicine</a>
                    @endif
                    <a href="{{ route('stock.in', ['id' => $medicine['medicine_id']]) }}" class="btn {{ is_admin() ? '' : 'btn-primary' }} receipt-btn">Stock-In</a>
                    <a href="{{ route('stock.adjust', ['id' => $medicine['medicine_id']]) }}" class="btn receipt-btn">{{ is_admin() ? 'Adjust Stock' : 'Request Adjustment' }}</a>

                    @if (is_admin() && $medicine['is_archived'] == 0)
                        <form method="POST" action="{{ route('medicines.save') }}" onsubmit="return confirm('Archive this medicine?');">
                            @csrf
                            <input type="hidden" name="medicine_id" value="{{ $medicine['medicine_id'] }}">
                            <input type="hidden" name="action" value="archive">
                            <button type="submit" class="btn btn-danger-outline receipt-btn">Archive Medicine</button>
                        </form>
                    @elseif ($medicine['is_archived'] == 1)
                        <span class="pill pill-amber">This medicine is archived</span>
                    @endif
                </div>
            </div>
        </div>
@endsection
