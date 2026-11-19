@extends('layouts.app')

@section('content')
        <form method="POST" action="{{ route('settings.save') }}">
            @csrf

            <div class="settings-grid">

                <!-- left column -->
                <div class="settings-col">

                    <div class="card form-card">
                        <h3 class="form-title">Pharmacy Profile</h3>

                        <div class="field">
                            <label>Pharmacy name <span class="required">*</span></label>
                            <input type="text" name="pharmacy_name" class="input{{ error_class($field_errors, 'pharmacy_name') }}" value="{{ $form['pharmacy_name'] }}">
                            {!! show_error($field_errors, 'pharmacy_name') !!}
                        </div>

                        <div class="field">
                            <label>Address</label>
                            <input type="text" name="address" class="input{{ error_class($field_errors, 'address') }}" value="{{ $form['address'] }}">
                            {!! show_error($field_errors, 'address') !!}
                        </div>

                        <div class="field-row">
                            <div class="field">
                                <label>Contact number</label>
                                <input type="text" name="contact_number" class="input{{ error_class($field_errors, 'contact_number') }}" value="{{ $form['contact_number'] }}">
                                {!! show_error($field_errors, 'contact_number') !!}
                            </div>

                            <div class="field">
                                <label>Email</label>
                                <input type="text" name="email" class="input{{ error_class($field_errors, 'email') }}" value="{{ $form['email'] }}">
                                {!! show_error($field_errors, 'email') !!}
                            </div>
                        </div>
                    </div>

                    <div class="card form-card">
                        <h3 class="form-title">Receipt Settings</h3>

                        <div class="field">
                            <label>Receipt header</label>
                            <input type="text" name="receipt_header" class="input{{ error_class($field_errors, 'receipt_header') }}" value="{{ $form['receipt_header'] }}">
                            {!! show_error($field_errors, 'receipt_header') !!}
                        </div>

                        <div class="field">
                            <label>Receipt footer</label>
                            <input type="text" name="receipt_footer" class="input{{ error_class($field_errors, 'receipt_footer') }}" value="{{ $form['receipt_footer'] }}">
                            {!! show_error($field_errors, 'receipt_footer') !!}
                        </div>

                        <div class="switch-row">
                            <div>
                                <strong>Print receipt automatically</strong>
                                <span>Open print dialog after every completed sale</span>
                            </div>
                            <label class="switch"><input type="checkbox" name="print_receipt" {{ $form['print_receipt'] == 1 ? 'checked' : '' }}><span class="slider"></span></label>
                        </div>
                    </div>
                </div>

                <!-- right column -->
                <div class="settings-col">

                    <div class="card form-card">
                        <h3 class="form-title">Inventory Defaults</h3>

                        <div class="field-row">
                            <div class="field">
                                <label>Default minimum stock (units)</label>
                                <input type="text" name="default_min" class="input{{ error_class($field_errors, 'default_min') }}" value="{{ $form['default_min'] }}">
                                {!! show_error($field_errors, 'default_min') !!}
                            </div>

                            <div class="field">
                                <label>Default maximum stock (units)</label>
                                <input type="text" name="default_max" class="input{{ error_class($field_errors, 'default_max') }}" value="{{ $form['default_max'] }}">
                                {!! show_error($field_errors, 'default_max') !!}
                            </div>
                        </div>

                        <div class="field">
                            <label>Near-expiry window</label>
                            <div class="segmented seg-left">
                                <label class="pay-option"><input type="radio" name="expiry_choice" value="30" {{ $expiry_choice == '30' ? 'checked' : '' }} onclick="expiryChanged()"><span>30 days</span></label>
                                <label class="pay-option"><input type="radio" name="expiry_choice" value="60" {{ $expiry_choice == '60' ? 'checked' : '' }} onclick="expiryChanged()"><span>60 days</span></label>
                                <label class="pay-option"><input type="radio" name="expiry_choice" value="90" {{ $expiry_choice == '90' ? 'checked' : '' }} onclick="expiryChanged()"><span>90 days</span></label>
                                <label class="pay-option"><input type="radio" name="expiry_choice" value="custom" {{ $expiry_choice == 'custom' ? 'checked' : '' }} onclick="expiryChanged()"><span>Custom</span></label>
                            </div>
                            <div id="customBox" class="custom-days" style="display: {{ $expiry_choice == 'custom' ? 'flex' : 'none' }}">
                                <input type="text" name="custom_days" id="customDays" class="input days-input{{ error_class($field_errors, 'expiry_days') }}" value="{{ $expiry_choice == 'custom' ? $form['expiry_days'] : '' }}">
                                <span>days</span>
                            </div>
                            {!! show_error($field_errors, 'expiry_days') !!}
                        </div>
                    </div>

                </div>
            </div>

            <div class="form-actions settings-actions">
                <button type="submit" formaction="{{ route('settings.reset') }}" class="btn" onclick="return confirm('Put all settings back to the default values?');">Reset to Defaults</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
@endsection

@push('scripts')
<script>
// show the days box only when "Custom" is selected
function expiryChanged() {
    var choice = document.querySelector("input[name=expiry_choice]:checked").value;
    document.getElementById("customBox").style.display = (choice == "custom") ? "flex" : "none";
}
</script>
@endpush
