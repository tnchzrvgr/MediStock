@extends('layouts.app')

@section('content')
        <div class="form-columns">

            <!-- supplier form -->
            <form method="POST" action="{{ route('suppliers.save') }}" class="card form-card">
                @csrf
                <input type="hidden" name="supplier_id" value="{{ $supplier_id }}">

                <h3 class="form-title">Supplier Details</h3>

                <div class="field-row">
                    <div class="field">
                        <label>Supplier name <span class="required">*</span></label>
                        <input type="text" name="supplier_name" class="input{{ error_class($field_errors, 'supplier_name') }}" value="{{ $form['supplier_name'] }}">
                        {!! show_error($field_errors, 'supplier_name') !!}
                    </div>

                    <div class="field">
                        <label>Contact person <span class="required">*</span></label>
                        <input type="text" name="contact_person" class="input{{ error_class($field_errors, 'contact_person') }}" value="{{ $form['contact_person'] }}">
                        {!! show_error($field_errors, 'contact_person') !!}
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Phone <span class="required">*</span></label>
                        <input type="text" name="phone" class="input{{ error_class($field_errors, 'phone') }}" value="{{ $form['phone'] }}">
                        {!! show_error($field_errors, 'phone') !!}
                    </div>

                    <div class="field">
                        <label>Email</label>
                        <input type="text" name="email" class="input{{ error_class($field_errors, 'email') }}" value="{{ $form['email'] }}">
                        {!! show_error($field_errors, 'email') !!}
                    </div>
                </div>

                <div class="field">
                    <label>Address <span class="required">*</span></label>
                    <input type="text" name="address" class="input{{ error_class($field_errors, 'address') }}" value="{{ $form['address'] }}">
                    {!! show_error($field_errors, 'address') !!}
                </div>

                <div class="divider"></div>

                <h3 class="form-title">Products Supplied</h3>

                <div class="field">
                    <div class="chip-row">
                        @foreach ($product_list as $product)
                            <label class="chip">
                                <input type="checkbox" name="products[]" value="{{ $product }}" {{ in_array($product, $form['products']) ? 'checked' : '' }}>
                                <span>{{ $product }}</span>
                            </label>
                        @endforeach
                    </div>
                    {!! show_error($field_errors, 'products') !!}
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Payment terms</label>
                        <select name="payment_terms" class="input{{ error_class($field_errors, 'payment_terms') }}">
                            @foreach ($terms as $t)
                                <option value="{{ $t }}" {{ ($form['payment_terms'] == $t) ? 'selected' : '' }}>{{ $t }}</option>
                            @endforeach
                        </select>
                        {!! show_error($field_errors, 'payment_terms') !!}
                    </div>

                    <div class="field">
                        <label>Delivery lead time (days)</label>
                        <input type="text" name="lead_time_days" class="input{{ error_class($field_errors, 'lead_time_days') }}" value="{{ $form['lead_time_days'] }}" placeholder="3">
                        {!! show_error($field_errors, 'lead_time_days') !!}
                    </div>
                </div>

                <div class="field">
                    <label>Status</label>
                    <select name="status" class="input">
                        @foreach (array("Active", "On Hold", "Inactive") as $option)
                            <option value="{{ $option }}" {{ $form['status'] == $option ? 'selected' : '' }}>{{ $option }}</option>
                        @endforeach
                    </select>
                    <span class="field-hint">Active: can be chosen in Stock-In. On Hold: paused for now. Inactive: no longer used.</span>
                </div>

                <div class="field">
                    <label>Notes</label>
                    <textarea name="notes" class="input textarea{{ error_class($field_errors, 'notes') }}" rows="3">{{ $form['notes'] }}</textarea>
                    {!! show_error($field_errors, 'notes') !!}
                </div>

                <div class="form-actions">
                    <a href="{{ route('suppliers') }}" class="btn">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Supplier</button>
                </div>
            </form>

            <!-- right side card -->
            <div class="side-col">
                <div class="card side-card">
                    <h3 class="card-title">Before you save</h3>
                    <ul class="tip-list">
                        <li>Duplicate suppliers are checked by name and phone.</li>
                        <li>Select every category this supplier provides so reorder suggestions can use them.</li>
                        <li>You can edit or deactivate a supplier at any time.</li>
                    </ul>
                </div>
            </div>
        </div>
@endsection
