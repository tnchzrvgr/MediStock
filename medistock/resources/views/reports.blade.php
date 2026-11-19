@extends('layouts.app')

@section('content')
        <!-- the 6 report cards -->
        <div class="report-cards no-print">
            @foreach ($report_list as $key => $info)
                <div class="card report-card {{ ($report == $key) ? 'selected' : '' }}">
                    <h4>{{ $info[0] }}</h4>
                    <p>{{ $info[1] }}</p>
                    @if ($report == $key)
                        <span class="btn btn-primary btn-small">Selected</span>
                    @else
                        <a href="{{ route('reports') }}?report={{ $key . $keep }}" class="btn btn-small">Generate</a>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- the report -->
        <div class="card report-panel">

            <div class="report-top no-print">
                <form method="GET" action="{{ route('reports') }}" class="report-filters">
                    <div class="field">
                        <label>Report</label>
                        <select name="report" class="input" onchange="this.form.submit()">
                            @foreach ($report_list as $key => $info)
                                <option value="{{ $key }}" {{ ($report == $key) ? "selected" : '' }}>{{ $info[0] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label>Date</label>
                        <input type="date" name="date" class="input" value="{{ $date }}" onchange="this.form.submit()">
                    </div>

                    <div class="field">
                        <label>Staff</label>
                        <select name="staff" class="input" onchange="this.form.submit()">
                            <option value="0">All staff</option>
                            @foreach ($staff_list as $s)
                                <option value="{{ $s['user_id'] }}" {{ ($staff == $s['user_id']) ? "selected" : '' }}>{{ $s['full_name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div class="report-buttons">
                    <button type="button" class="btn btn-primary" onclick="window.print()">Export PDF</button>
                    <a href="{{ route('reports') }}?report={{ $report . $keep }}&export=csv" class="btn">Export Excel</a>
                    <button type="button" class="btn" onclick="window.print()">Print</button>
                </div>
            </div>

            <h3 class="report-title">{{ $title }}</h3>

            <div class="table-wrap">
                <table class="data-table">
                    <tr>
                        @foreach ($columns as $col)
                            <th>{{ $col }}</th>
                        @endforeach
                    </tr>

                    @if (count($rows) == 0)
                        <tr><td colspan="{{ count($columns) }}" class="empty-row">No records for this report</td></tr>
                    @endif

                    @foreach ($rows as $r)
                        <tr>
                            @foreach ($r as $i => $cell)
                                <td class="{{ ($i == 0) ? 'cell-strong' : '' }}">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            </div>

            <div class="report-footer">
                <span>{{ $footer_left }}</span>
                <strong>{{ $footer_right }}</strong>
            </div>
        </div>
@endsection
