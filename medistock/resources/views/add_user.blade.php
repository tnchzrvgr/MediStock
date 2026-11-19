@extends('layouts.app')

@section('content')
        <div class="form-columns">

            <form method="POST" action="{{ route('users.save') }}" class="card form-card">
@csrf
                <input type="hidden" name="user_id" value="{{ $user_id }}">

                <h3 class="form-title">Account Details</h3>

                <div class="field-row">
                    <div class="field">
                        <label>Full name <span class="required">*</span></label>
                        <input type="text" name="full_name" class="input{{ error_class($field_errors, 'full_name') }}" value="{{ $form['full_name'] }}">
                        {!! show_error($field_errors, 'full_name') !!}
                    </div>

                    <div class="field">
                        <label>Role <span class="required">*</span></label>
                        <select name="role" class="input{{ error_class($field_errors, 'role') }}">
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" {{ ($form['role'] == $value) ? "selected" : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        {!! show_error($field_errors, 'role') !!}
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" class="input{{ error_class($field_errors, 'username') }}" value="{{ $form['username'] }}">
                        {!! show_error($field_errors, 'username') !!}
                    </div>

                    <div class="field">
                        <label>Email <span class="required">*</span></label>
                        <input type="text" name="email" class="input{{ error_class($field_errors, 'email') }}" value="{{ $form['email'] }}">
                        {!! show_error($field_errors, 'email') !!}
                    </div>
                </div>

                <hr class="divider">

                <h3 class="form-title">Password</h3>

                <div class="field-row">
                    <div class="field">
                        <label>Password {!! ($user_id == 0) ? '<span class="required">*</span>' : '' !!}</label>
                        <input type="password" name="password" class="input{{ error_class($field_errors, 'password') }}">
                        {!! show_error($field_errors, 'password') !!}
                        @if ($user_id > 0)
                            <span class="field-hint">Leave empty to keep the current password.</span>
                        @endif
                    </div>

                    <div class="field">
                        <label>Confirm password</label>
                        <input type="password" name="password2" class="input{{ error_class($field_errors, 'password2') }}">
                        {!! show_error($field_errors, 'password2') !!}
                    </div>
                </div>

                <div class="form-actions">
                    <a href="{{ route('users') }}" class="btn">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save User</button>
                </div>
            </form>

            <!-- what the roles can open -->
            <div class="side-col">
                <div class="card side-card">
                    <h3 class="card-title">Role access</h3>
                    <p class="side-text"><strong>Owner / Admin:</strong> reports, analytics, suppliers, medicine records, stock adjustment approval, user management and the activity log.</p>
                    <p class="side-text"><strong>Pharmacy Staff:</strong> sales, stock checking, alerts, stock-in on delivery and stock adjustment requests.</p>
                </div>
            </div>

        </div>
@endsection
