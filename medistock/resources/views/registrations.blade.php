@extends('layouts.app')

@section('content')
@if (session('reg_msg'))
    <div class="flash flash-info">{{ session('reg_msg') }}</div>
@endif

<div class="table-card">
    <div class="card-head">
        <div>
            <div class="card-title">Registrations</div>
            <div class="card-sub">New staff who passed the ID scan and face matching. Approve to create their account (role: Staff).</div>
        </div>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th><th>Username</th><th>ID</th><th>OCR</th><th>Face score</th><th>Fields edited</th><th>Applied</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr>
                        <td>{{ $r['full_name'] }}<br><small>{{ $r['age'] }} yrs, {{ $r['address'] }}</small></td>
                        <td>{{ $r['username'] }}<br><small>{{ $r['email'] }}</small></td>
                        <td>{{ $r['id_type'] }}<br><small>{{ $r['id_number_masked'] }}</small></td>
                        <td>{{ $r['ocr_confidence'] }}%</td>
                        <td>{{ $r['face_score'] }}</td>
                        <td>{{ $r['fields_edited'] }}</td>
                        <td>{{ date('M d, Y', strtotime($r['created_at'])) }}</td>
                        <td>{{ $r['status'] }}</td>
                        <td>
                            @if ($r['status'] == 'Pending')
                                <form method="POST" action="{{ route('registrations.decide', $r['reg_id']) }}" style="display:flex; gap:6px;">
                                    @csrf
                                    <button type="submit" name="decision" value="Approved" class="btn-small btn-primary">Approve</button>
                                    <button type="submit" name="decision" value="Rejected" class="btn-small btn-danger-outline">Reject</button>
                                </form>
                            @else
                                {{ $r['decided_by'] }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9">No registrations yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
