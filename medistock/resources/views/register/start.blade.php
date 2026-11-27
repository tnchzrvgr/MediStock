@extends('register.layout')

@section('active', '2')

@section('content')
<div class="form-heading">
    <h2>Add new user</h2>
    <p>Step 2: choose the kind of ID the new user will use. The ID and the face are checked first, then the information is filled in for you.</p>
</div>

<form method="POST" action="{{ route('register.consent') }}">
    @csrf

    <div class="field">
        <label for="id_type">Kind of ID</label>
        <select name="id_type" id="id_type">
            <option value="">Choose a supported ID...</option>
            @foreach ($types as $key => $name)
                <option value="{{ $key }}">{{ $name }}</option>
            @endforeach
        </select>
        <p class="small-note">Only valid government-issued IDs are supported.</p>
    </div>

    <div class="card-note">
        <strong>Data privacy consent</strong>
        <p>The photo of the ID and the face are processed inside the browser. MediStock saves only the reviewed information, a masked ID number (last 4 characters) and the face numbers used for matching. The pictures are never saved.</p>
        <label class="remember">
            <input type="checkbox" name="agree" value="1">
            The person being added agrees to the ID and face verification.
        </label>
    </div>

    <button type="submit" class="btn-signin">Continue</button>
</form>

<p class="back-link"><a href="{{ route('users') }}">Back to User Management</a></p>
@endsection
