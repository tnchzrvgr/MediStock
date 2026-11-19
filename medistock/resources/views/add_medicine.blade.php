@extends('layouts.app')

@section('content')
        <div class="form-columns">

            <!-- medicine form -->
            <form method="POST" action="{{ route('medicines.save') }}" class="card form-card">
@csrf
                <input type="hidden" name="medicine_id" value="{{ $medicine_id }}">

                <h3 class="form-title">Medicine Details</h3>

                <div class="field-row">
                    <div class="field">
                        <label>Medicine name <span class="required">*</span></label>
                        <input type="text" name="medicine_name" class="input{{ error_class($field_errors, 'medicine_name') }}{{ $duplicate ? ' input-error' : '' }}" value="{{ $form['medicine_name'] }}">
                        {!! show_error($field_errors, 'medicine_name') !!}
                        @if ($duplicate)
                            <span class="field-error">A medicine with this name, generic name and unit already exists (duplicate check).</span>
                        @endif
                    </div>

                    <div class="field">
                        <label>Generic name <span class="required">*</span></label>
                        <input type="text" name="generic_name" class="input{{ error_class($field_errors, 'generic_name') }}" value="{{ $form['generic_name'] }}">
                        {!! show_error($field_errors, 'generic_name') !!}
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Category <span class="required">*</span></label>
                        <select name="category_id" class="input{{ error_class($field_errors, 'category_id') }}">
                            <option value="">Select category</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat['category_id'] }}" {{ ($form['category_id'] == $cat['category_id']) ? "selected" : '' }}>
                                    {{ $cat['category_name'] }}
                                </option>
                            @endforeach
                        </select>
                        {!! show_error($field_errors, 'category_id') !!}
                    </div>

                    <div class="field">
                        <label>Unit <span class="required">*</span></label>
                        <select name="unit" class="input">
                            @php
// if the medicine has an older unit that is not in the list, add it
                            if (!in_array($form['unit'], $units)) {
                                $units[] = $form['unit'];
                            }
@endphp
@foreach ($units as $u)
                                <option value="{{ $u }}" {{ ($form['unit'] == $u) ? "selected" : '' }}>
                                    {{ $u }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Unit price (₱) <span class="required">*</span></label>
                        <input type="text" name="unit_price" class="input{{ error_class($field_errors, 'unit_price') }}" value="{{ $form['unit_price'] }}">
                        {!! show_error($field_errors, 'unit_price') !!}
                    </div>

                    <div class="field">
                        <label>Quantity on hand <span class="required">*</span></label>
                        <input type="text" id="qty" name="quantity" class="input{{ error_class($field_errors, 'quantity') }}" value="{{ $form['quantity'] }}" oninput="updatePreview()">
                        {!! show_error($field_errors, 'quantity') !!}
                    </div>
                </div>

                <hr class="divider">

                <h3 class="form-title">Stock Levels</h3>

                <div class="field-row">
                    <div class="field">
                        <label>Minimum stock level <span class="required">*</span></label>
                        <input type="text" id="min" name="reorder_level" class="input{{ error_class($field_errors, 'reorder_level') }}" value="{{ $form['reorder_level'] }}" oninput="updatePreview()">
                        {!! show_error($field_errors, 'reorder_level') !!}
                        <span class="field-hint">Alert when stock is at or below this level.</span>
                    </div>

                    <div class="field">
                        <label>Maximum stock level <span class="required">*</span></label>
                        <input type="text" id="max" name="max_stock" class="input{{ error_class($field_errors, 'max_stock') }}" value="{{ $form['max_stock'] }}" oninput="updatePreview()">
                        {!! show_error($field_errors, 'max_stock') !!}
                        <span class="field-hint">Flag as overstock when above this level.</span>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('inventory') }}" class="btn">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Medicine</button>
                </div>
            </form>

            <!-- right side cards -->
            <div class="side-col">

                <div class="card side-card">
                    <h3 class="card-title">Stock Status Preview</h3>

                    <div class="bars">
                        <span class="bar bar-low"></span>
                        <span class="bar bar-normal"></span>
                        <span class="bar bar-over"></span>
                    </div>

                    <div class="legend">
                        <span class="pill pill-danger">Low</span>
                        <span class="pill pill-up">Normal</span>
                        <span class="pill pill-amber">Overstock</span>
                    </div>

                    <div class="preview-box {{ $preview_class }}" id="previewBox">
                        <span id="previewText">{{ $preview_text }}</span>
                        <strong id="previewLabel">{{ $preview_label }}</strong>
                    </div>
                </div>

                @if ($medicine_id > 0)
                    <div class="card side-card">
                        <h3 class="card-title">Archive Medicine</h3>
                        <p class="side-text">Archived medicines are hidden from sales and stock lists, but all history is kept.</p>
                        <form method="POST" action="{{ route('medicines.save') }}" onsubmit="return confirm('Archive this medicine?');">
@csrf
                            <input type="hidden" name="medicine_id" value="{{ $medicine_id }}">
                            <input type="hidden" name="action" value="archive">
                            <button type="submit" class="btn btn-danger-outline">Archive Medicine</button>
                        </form>
                    </div>
                @endif

            </div>
        </div>
@endsection

@push('scripts')
<script>

// changes the Stock Status Preview while the user is typing
function updatePreview() {
    var qtyText = document.getElementById("qty").value;
    var qty = parseInt(qtyText);
    var min = parseInt(document.getElementById("min").value);
    var max = parseInt(document.getElementById("max").value);

    if (isNaN(min)) { min = 0; }
    if (isNaN(max)) { max = 0; }

    var box = document.getElementById("previewBox");
    var text = document.getElementById("previewText");
    var label = document.getElementById("previewLabel");

    if (qtyText == "" || isNaN(qty)) {
        text.innerHTML = "No quantity yet";
        label.innerHTML = "—";
        box.className = "preview-box preview-normal";
        return;
    }

    text.innerHTML = "Current: " + qty + " units (min " + min + " · max " + max + ")";

    if (qty == 0) {
        label.innerHTML = "OUT";
        box.className = "preview-box preview-low";
    } else if (qty <= min) {
        label.innerHTML = "LOW";
        box.className = "preview-box preview-low";
    } else if (max > 0 && qty > max) {
        label.innerHTML = "OVERSTOCK";
        box.className = "preview-box preview-over";
    } else {
        label.innerHTML = "NORMAL";
        box.className = "preview-box preview-normal";
    }
}
</script>
@endpush
