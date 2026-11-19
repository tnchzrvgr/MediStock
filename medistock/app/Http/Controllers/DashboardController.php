<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Dashboard page
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // get the data from the stored procedures
        $kpi_rows = Proc::call('sp_dashboard_kpis');
        $low_rows = Proc::call('sp_low_stock_expiring');
        $sales_rows = Proc::call('sp_recent_sales');
        $trend_rows = Proc::call('sp_sales_trend');
        $demand_rows = Proc::call('sp_top_demand');

        if (count($kpi_rows) == 0) {
            abort(500, "No dashboard data found. Please run dashboard.sql in phpMyAdmin first.");
        }
        $kpi = $kpi_rows[0];

        // sales change compared to the previous 30 days
        if ($kpi['sales_prev_30_days'] > 0) {
            $change = (($kpi['sales_30_days'] - $kpi['sales_prev_30_days']) / $kpi['sales_prev_30_days']) * 100;
        } else {
            $change = 0;
        }

        if ($change >= 0) {
            $change_class = "pill-up";
            $change_arrow = "▲";
        } else {
            $change_class = "pill-down";
            $change_arrow = "▼";
        }
        $change_text = $change_arrow . " " . number_format(abs($change), 1) . "% vs last month";

        // total of the last 30 days (example: ₱ 1.24M total)
        if ($kpi['sales_30_days'] >= 1000000) {
            $total_30_text = "₱ " . number_format($kpi['sales_30_days'] / 1000000, 2) . "M total";
        } else {
            $total_30_text = "₱ " . number_format($kpi['sales_30_days'], 2) . " total";
        }

        // data for the line chart
        $trend_labels = array();
        $trend_values = array();
        foreach ($trend_rows as $row) {
            $trend_labels[] = date("M j", strtotime($row['sale_day']));
            $trend_values[] = (float) $row['daily_total'];
        }

        // data for the bar chart (short names under the bars like the design)
        $demand_labels = array();
        $demand_names = array();
        $demand_values = array();
        foreach ($demand_rows as $row) {
            $demand_labels[] = substr($row['medicine_name'], 0, 4);
            $demand_names[] = $row['medicine_name'];
            $demand_values[] = (int) $row['total_sold'];
        }

        // page title and subtitle for the header
        $page_title = "Dashboard";
        $page_subtitle = date("l, M j") . " — here's what's happening across your pharmacy today";
        $current_page = "dashboard";


        return view('dashboard', compact('low_rows', 'sales_rows', 'kpi', 'change_class', 'change_text', 'total_30_text', 'trend_labels', 'trend_values', 'demand_labels', 'demand_names', 'demand_values', 'page_title', 'page_subtitle', 'current_page'));
    }
}
