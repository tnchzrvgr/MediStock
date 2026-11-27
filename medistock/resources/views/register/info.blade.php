@extends('register.layout')

@section('active', '6')

@section('content')
<div class="form-heading">
    <h2>Personal information</h2>
    <p>ID and face are verified. Please check the details filled in from your {{ $type_name }} and correct anything that is wrong.</p>
</div>

@if ($errors->any())
    <div class="notice error" style="display:block;">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('register.info.save') }}">
    @csrf

    <h3 class="result-title">From your ID</h3>

    <div class="field">
        <label for="full_name">Full name</label>
        <input type="text" name="full_name" id="full_name" value="{{ old('full_name', $reg['name']) }}">
    </div>

    <div class="two-cols">
        <div class="field">
            <label for="birth_date">Date of birth</label>
            <input type="date" name="birth_date" id="birth_date" value="{{ old('birth_date', $reg['dob']) }}">
        </div>
        <div class="field">
            <label for="age">Age (automatic)</label>
            <input type="text" id="age" value="{{ $age }}" readonly>
        </div>
    </div>

    <div class="field">
        <label for="address">Address</label>
        <input type="text" name="address" id="address" value="{{ old('address', $reg['address']) }}" placeholder="House no., street, barangay, city">
    </div>

    <div class="field">
        <label for="id_number">ID number ({{ $type_name }})</label>
        <input type="text" name="id_number" id="id_number" value="{{ old('id_number', $reg['id_number']) }}">
    </div>

    <h3 class="result-title">MediStock account</h3>

    <div class="field">
        <label for="role">Role</label>
        <select name="role" id="role">
            <option value="Staff" {{ old('role') == 'Staff' ? 'selected' : '' }}>Pharmacy Staff</option>
            <option value="Admin" {{ old('role') == 'Admin' ? 'selected' : '' }}>Owner / Admin</option>
        </select>
    </div>

    <div class="field">
        <label for="username">Username</label>
        <input type="text" name="username" id="username" value="{{ old('username') }}">
    </div>

    <div class="field">
        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}">
    </div>

    <div class="two-cols">
        <div class="field">
            <label for="password">Password (8 or more characters)</label>
            <input type="password" name="password" id="password">
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <input type="password" name="password_confirmation" id="password_confirmation">
        </div>
    </div>

    <label class="remember" style="margin-bottom:18px;">
        <input type="checkbox" name="confirm_info" value="1">
        I checked the information above and it is correct.
    </label>

    <button type="submit" class="btn-signin">Save new user</button>
</form>
@endsection

@section('scripts')
<script>
// age is calculated again when the birth date is changed
document.getElementById("birth_date").addEventListener("change", function () {
    var p = this.value.split("-");
    if (p.length != 3) { document.getElementById("age").value = ""; return; }
    var today = new Date();
    var age = today.getFullYear() - parseInt(p[0], 10);
    var m = (today.getMonth() + 1) - parseInt(p[1], 10);
    if (m < 0 || (m == 0 && today.getDate() < parseInt(p[2], 10))) { age--; }
    document.getElementById("age").value = age;
});
</script>
@endsection
