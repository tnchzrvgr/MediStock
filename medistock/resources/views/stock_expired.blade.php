@extends('layouts.app')

@section('content')
        <div class="card table-card">
            <h3 class="card-title">Expired Medicines ({{ count($expired) }})</h3>

            @if (isset($field_errors['_all']))
                <div class="flash flash-error">{{ $field_errors['_all'] }}</div>
            @endif

            @if (count($expired) == 0)
                <p class="side-text">No expired medicines with stock. Nothing to remove.</p>
                <div class="form-actions">
                    <a href="{{ route('stock.adjust') }}" class="btn">Back</a>
                </div>
            @else
                <p class="side-text">Check the medicines to remove. The quantity is an estimate of the expired units, so please count the shelf and change it if needed.{{ is_admin() ? '' : ' The owner must approve before the stock changes.' }}</p>

                <form method="POST" action="{{ route('stock.expired.save') }}">
                    @csrf
                    <div class="table-wrap">
                        <table class="data-table">
                            <tr>
                                <th></th>
                                <th>MEDICINE</th>
                                <th>BATCH NO.</th>
                                <th>EXPIRED ON</th>
                                <th>STOCK NOW</th>
                                <th>UNITS TO REMOVE</th>
                            </tr>

                            @foreach ($expired as $row)
                                <tr>
                                    <td><input type="checkbox" name="pick[]" value="{{ $row['medicine_id'] }}" checked></td>
                                    <td class="cell-strong">{{ $row['medicine_name'] }}</td>
                                    <td>{{ $row['batch_no'] }}</td>
                                    <td class="status-red">{{ date("M d, Y", strtotime($row['expiry_date'])) }}</td>
                                    <td>{{ $row['quantity'] . " " . unit_text($row['unit'], $row['quantity']) }}</td>
                                    <td>
                                        <input type="number" name="qty[{{ $row['medicine_id'] }}]" min="1" max="{{ $row['quantity'] }}" value="{{ $row['expired_qty'] }}" class="input days-input">
                                        @if (isset($field_errors[$row['medicine_id']]))
                                            <span class="field-error">{{ $field_errors[$row['medicine_id']] }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                    </div>

                    <div class="form-actions">
                        <a href="{{ route('stock.adjust') }}" class="btn">Cancel</a>
                        <button type="submit" class="btn btn-primary" onclick="return confirm('{{ is_admin() ? 'Remove the checked medicines from stock?' : 'Send the checked medicines to the owner for removal?' }}');">{{ is_admin() ? 'Remove Checked' : 'Send Request' }}</button>
                    </div>
                </form>
            @endif
        </div>
@endsection
