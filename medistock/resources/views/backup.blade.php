@extends('layouts.app')

@section('content')
        <!-- 4 cards -->
        <div class="kpi-row">
            <div class="card kpi-card">
                <span class="kpi-label">Last Backup</span>
                <span class="kpi-value kpi-value-small">{{ $last_text }}</span>
                <span class="pill {{ $last_class }}">{{ $last_status }}</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Backup Size</span>
                <span class="kpi-value kpi-value-small">{{ $size_text }}</span>
                <span class="pill pill-blue">Database (tables)</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Next Scheduled</span>
                <span class="kpi-value kpi-value-small">{{ $next_text }}</span>
                <span class="pill pill-up">{{ $next_note }}</span>
            </div>

            <div class="card kpi-card">
                <span class="kpi-label">Session Timeout</span>
                <span class="kpi-value kpi-value-small">{{ $settings['session_timeout'] }} minutes</span>
                <span class="pill pill-amber">Auto logout on inactivity</span>
            </div>
        </div>

        <!-- backup settings and security settings -->
        <div class="two-col">

            <div class="card form-card">
                <h3 class="form-title">Database Backup</h3>

                <div class="button-row">
                    <form method="POST" action="{{ route('backup.now') }}">
                        @csrf
                        <button type="submit" class="btn btn-primary">Back Up Now</button>
                    </form>

                    <form method="POST" action="{{ route('backup.restore.file') }}" enctype="multipart/form-data" id="restoreForm">
                        @csrf
                        <input type="file" name="backup_file" id="backupFile" accept=".sql" class="hidden-file" onchange="restoreChosen()">
                        <button type="button" class="btn" onclick="document.getElementById('backupFile').click()">Restore from File</button>
                    </form>
                </div>

                <form method="POST" action="{{ route('backup.save') }}" class="settings-form">
                    @csrf
                    <input type="hidden" name="section" value="backup">

                    <div class="field">
                        <label>Backup schedule</label>
                        <div class="segmented seg-left">
                            @foreach ($schedules as $s)
                                <label class="pay-option"><input type="radio" name="schedule" value="{{ $s }}" {{ ($settings['schedule'] == $s) ? 'checked' : '' }}><span>{{ $s }}</span></label>
                            @endforeach
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field">
                            <label>Time</label>
                            <select name="backup_time" class="input">
                                @foreach ($times as $value => $label)
                                    <option value="{{ $value }}" {{ ($settings['backup_time'] == $value) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="field">
                            <label>Keep last</label>
                            <select name="keep_last" class="input">
                                @foreach ($keep_options as $k)
                                    <option value="{{ $k }}" {{ ($settings['keep_last'] == $k) ? 'selected' : '' }}>{{ $k }} backups</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <p class="field-hint">Automatic backups need <strong>php artisan schedule:work</strong> running (weekly = every Monday).</p>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>

            <form method="POST" action="{{ route('backup.save') }}" class="card form-card">
                @csrf
                <input type="hidden" name="section" value="security">

                <h3 class="form-title">Session Security</h3>

                <div class="field">
                    <label>Session timeout</label>
                    <div class="segmented seg-left">
                        @foreach ($timeouts as $minutes => $label)
                            <label class="pay-option"><input type="radio" name="session_timeout" value="{{ $minutes }}" {{ ($settings['session_timeout'] == $minutes) ? 'checked' : '' }}><span>{{ $label }}</span></label>
                        @endforeach
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>

        <!-- backup history table -->
        <div class="card table-card">
            <h3 class="card-title">Backup History</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        <th>DATE &amp; TIME</th>
                        <th>TYPE</th>
                        <th>SIZE</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>

                    @if (count($history_rows) == 0)
                        <tr><td colspan="5" class="empty-row">No backups yet. Click Back Up Now to make the first one.</td></tr>
                    @endif

                    @foreach ($history_rows as $row)
                        <tr>
                            <td class="cell-strong">{{ $row['date'] }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td>{{ $row['size'] }}</td>
                            <td class="{{ $row['status'] == 'Successful' ? 'status-green' : 'status-red' }}">{{ $row['status'] }}</td>
                            <td class="cell-actions">
                                @if ($row['status'] == 'Successful')
                                    <a href="{{ route('backup.download', ['id' => $row['id']]) }}">Download</a> ·
                                    <form method="POST" action="{{ route('backup.restore') }}" class="inline-form" onsubmit="return confirm('Restore this backup? The current data will be replaced.');">
                                        @csrf
                                        <input type="hidden" name="backup_id" value="{{ $row['id'] }}">
                                        <button type="submit" class="link-btn">Restore</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('backup.retry') }}" class="inline-form">
                                        @csrf
                                        <button type="submit" class="link-btn">Retry</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        </div>
@endsection

@push('scripts')
<script>
// after a backup file is chosen, ask first and then send the form
function restoreChosen() {
    var input = document.getElementById("backupFile");
    if (input.files.length == 0) {
        return;
    }
    if (confirm("Restore the database from " + input.files[0].name + "? The current data will be replaced.")) {
        document.getElementById("restoreForm").submit();
    } else {
        input.value = "";
    }
}
</script>
@endpush
