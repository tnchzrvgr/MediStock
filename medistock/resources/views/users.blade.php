@extends('layouts.app')

@section('content')
        <link rel="stylesheet" href="{{ asset('css/face.css') }}">

        <!-- 4 cards -->
        <div class="kpi-row">
            <div class="card kpi-card">
                <span class="kpi-label">Total Users</span>
                <span class="kpi-value">{{ number_format($kpi['total_users']) }}</span>
                <span class="pill pill-up">2 roles configured</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Active Accounts</span>
                <span class="kpi-value">{{ number_format($kpi['active_accounts']) }}</span>
                <span class="pill pill-up">Currently enabled</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Deactivated</span>
                <span class="kpi-value">{{ number_format($kpi['deactivated']) }}</span>
                <span class="pill pill-danger">Cannot log in</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Active Sessions</span>
                <span class="kpi-value">{{ number_format($kpi['active_sessions']) }}</span>
                <span class="pill pill-blue">Logged in now</span>
            </div>
        </div>

        <!-- what each role can open -->
        <div class="role-row">
            <div class="card role-card">
                <span class="pill pill-up">Owner / Admin</span>
                <p class="role-intro">Full access to every module</p>
                <ul>
                    <li>Reports &amp; analytics, suppliers</li>
                    <li>Add, edit and archive medicines</li>
                    <li>Approve stock adjustments</li>
                    <li>User management &amp; activity log</li>
                </ul>
            </div>

            <div class="card role-card">
                <span class="pill pill-blue">Pharmacy Staff</span>
                <p class="role-intro">Daily counter and stock work</p>
                <ul>
                    <li>Record sales and print receipts</li>
                    <li>Check stock and alerts</li>
                    <li>Record stock-in on delivery</li>
                    <li>Request stock adjustments</li>
                </ul>
            </div>
        </div>

        <!-- user accounts -->
        <div class="card table-card">
            <div class="card-head">
                <h3 class="card-title">User Accounts</h3>
                <a href="{{ route('users.form') }}" class="btn btn-primary">+ Add User</a>
            </div>

            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>NAME</th>
                        <th>EMAIL</th>
                        <th>ROLE</th>
                        <th>FACE ID</th>
                        <th>LAST LOGIN</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>

                    @foreach ($users as $row)
                        @php
// last login: "Today, 9:02 AM" or "Sep 18, 2026"
                        if ($row['last_login'] == "") {
                            $login_text = "Never";
                        } elseif (date("Y-m-d", strtotime($row['last_login'])) == date("Y-m-d")) {
                            $login_text = "Today, " . date("g:i A", strtotime($row['last_login']));
                        } else {
                            $login_text = date("M d, Y", strtotime($row['last_login']));
                        }
@endphp
                        <tr>
                            <td class="cell-strong">{{ $row['full_name'] }}</td>
                            <td class="cell-email">{{ $row['email'] }}</td>
                            <td>{{ role_label($row['role']) }}</td>
                            <td>
                                @if (isset($faces[$row['user_id']]))
                                    <div class="face-cell">
                                        <span class="status-green">Registered</span>
                                        <span class="face-links">
                                            <a href="{{ route('face.enroll') }}?id={{ $row['user_id'] }}">Update</a> ·
                                            <form method="POST" action="{{ route('face.remove') }}" class="inline-form">
@csrf
                                                <input type="hidden" name="user_id" value="{{ $row['user_id'] }}">
                                                <button type="submit" class="link-btn" onclick="return confirm('Remove the face of this user?');">Remove</button>
                                            </form>
                                        </span>
                                    </div>
                                @else
                                    <div class="face-cell">
                                        <span class="muted-text">Not registered</span>
                                        <span class="face-links"><a href="{{ route('face.enroll') }}?id={{ $row['user_id'] }}">Register</a></span>
                                    </div>
                                @endif
                            </td>
                            <td>{{ $login_text }}</td>
                            @if ($row['is_active'] == 1)
                                <td class="status-green">Active</td>
                            @else
                                <td class="status-red">Deactivated</td>
                            @endif
                            <td class="cell-actions">
                                <a href="{{ route('users.form') }}?id={{ $row['user_id'] }}">Edit</a> ·
                                @if ($row['user_id'] == session('user_id'))
                                    <span class="muted-text">You</span>
                                @else
                                    <form method="POST" action="{{ route('users.active') }}" class="inline-form">
@csrf
                                        <input type="hidden" name="user_id" value="{{ $row['user_id'] }}">
                                        @if ($row['is_active'] == 1)
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="link-btn" onclick="return confirm('Deactivate this account?');">Deactivate</button>
                                        @else
                                            <input type="hidden" name="action" value="reactivate">
                                            <button type="submit" class="link-btn">Reactivate</button>
                                        @endif
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>

        <!-- activity log -->
        <div class="card table-card">
            <h3 class="card-title">Activity Log (who changed what and when)</h3>

            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>TIMESTAMP</th>
                        <th>USER</th>
                        <th>ACTION</th>
                        <th>DETAILS</th>
                    </tr>

                    @if (count($log) == 0)
                        <tr><td colspan="4" class="empty-row">No activity yet</td></tr>
                    @endif

                    @foreach ($log as $row)
                        <tr>
                            <td>{{ date("M d, g:i A", strtotime($row['log_time'])) }}</td>
                            <td class="cell-strong">{{ $row['user_name'] }}</td>
                            <td class="{{ action_color($row['action']) }}">{{ $row['action'] }}</td>
                            <td>{{ $row['details'] }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
@endsection
