@extends('register.layout')

@section('active', '7')

@section('content')
<div class="form-heading">
    <h2>New user added</h2>
    <p>{{ $done['name'] }} was verified and the account was created.</p>
</div>

<div class="notice success" style="display:block;">
    ID verified and face matched. The account is active and can sign in now.
</div>

<table class="result-table">
    <tbody>
        <tr><td>ID verification</td><td>{{ $done['id_type'] }} ({{ $done['masked'] }})</td><td><span class="badge ok">Passed</span></td></tr>
        <tr><td>OCR reading confidence</td><td>{{ $done['ocr'] }}%</td><td><span class="badge ok">Passed</span></td></tr>
        <tr><td>Face matching</td><td>score {{ $done['score'] }} (smaller is closer)</td><td><span class="badge ok">Matched</span></td></tr>
        <tr><td>Fields corrected by you</td><td>{{ $done['edited'] }}</td><td><span class="badge warn">Recorded</span></td></tr>
        <tr><td>Username</td><td>{{ $done['username'] }}</td><td><span class="badge ok">{{ $done['role'] == 'Admin' ? 'Owner / Admin' : 'Pharmacy Staff' }}</span></td></tr>
    </tbody>
</table>

<p class="small-note">Reminder: reading the ID and matching the face does not prove that the ID is genuine. Always check the real ID in person when adding a user.</p>

<a href="{{ route('users') }}" class="btn-signin link-btn">Back to User Management</a>
<a href="{{ route('register') }}" class="btn-alt link-btn" style="margin-top:10px;">Add another user</a>
@endsection
