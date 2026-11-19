<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Analytics page
class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        // ---------------------------------------------------------
        // small helper functions
        // ---------------------------------------------------------

        // ---------------------------------------------------------
        // which period button is selected (only these values are allowed)
        // ---------------------------------------------------------
        $period = "monthly";
        $wanted = $request->query('period');
        if ($wanted == "daily" || $wanted == "weekly" || $wanted == "monthly") {
            $period = $wanted;
        }

        if ($period == "daily") {
            $now_label = "TODAY";
            $prev_label = "YESTERDAY";
            $compare_sub = "Today vs. yesterday";
        } elseif ($period == "weekly") {
            $now_label = "THIS WEEK";
            $prev_label = "LAST WEEK";
            $compare_sub = "This week vs. last week";
        } else {
            $now_label = "THIS MONTH";
            $prev_label = "LAST MONTH";
            $compare_sub = "This month vs. last month";
        }

        // ---------------------------------------------------------
        // get the data from the stored procedures
        // ---------------------------------------------------------
        $kpi_rows = Proc::call('sp_analytics_kpis');
        if (count($kpi_rows) == 0) {
            abort(500, "No analytics data found. Please run dashboard.sql, sales.sql and then analytics.sql in phpMyAdmin first.");
        }
        $kpi = $kpi_rows[0];

        $best_rows = Proc::call('sp_best_sellers');
        $category_rows = Proc::call('sp_category_sales');
        $trend_rows = Proc::call('sp_category_trend');
        $value_rows = Proc::call('sp_inventory_value');
        $movement_rows = Proc::call('sp_movement_list');

        // $period can only be daily, weekly or monthly (checked above), so it is safe to put in the query
        $compare_rows = Proc::call('sp_period_comparison', array($period));

        // the best selling medicine is the one we forecast
        $best_name = "—";
        $best_units = 0;
        $forecast_id = 0;
        $forecast_name = "";
        if (count($best_rows) > 0) {
            $name_parts = explode(" ", $best_rows[0]['medicine_name']);
            $best_name = $name_parts[0];
            $best_units = $best_rows[0]['units_now'];
            $forecast_id = (int) $best_rows[0]['medicine_id'];
            $forecast_name = $best_rows[0]['medicine_name'];
        }
        $weekly_rows = Proc::call('sp_weekly_demand', array($forecast_id));

        // ---------------------------------------------------------
        // card 1: sales this month (compared with the same days last month)
        // ---------------------------------------------------------
        list($growth_class, $growth_text) = change_words($kpi['month_sales'], $kpi['last_month_sales'], "last month");

        // ---------------------------------------------------------
        // card 2: medicines that will run out within 2 weeks (counted by the stored procedure)
        // ---------------------------------------------------------
        $running_out = (int) $kpi['running_out'];

        // ---------------------------------------------------------
        // demand forecast chart (units sold per week of the best seller)
        // ---------------------------------------------------------
        // 8 weeks of real sales: position 0 is the oldest week, position 7 is the last 7 days
        $weekly_units = array(0, 0, 0, 0, 0, 0, 0, 0);
        foreach ($weekly_rows as $row) {
            $position = 7 - (int) $row['weeks_ago'];
            if ($position >= 0 && $position <= 7) {
                $weekly_units[$position] = (int) $row['units'];
            }
        }

        $chart_labels = array();
        $actual_data = array();
        $forecast_data = array();

        // the 8 real weeks (the label is the first day of that week)
        for ($i = 0; $i < 8; $i++) {
            $weeks_ago = 7 - $i;
            $days_back = ($weeks_ago * 7) + 6;
            $chart_labels[] = date("M j", strtotime("-" . $days_back . " days"));
            $actual_data[] = $weekly_units[$i];

            if ($i == 7) {
                // the forecast line starts where the real line ends
                $forecast_data[] = $weekly_units[$i];
            } else {
                $forecast_data[] = null;
            }
        }

        // the next 4 weeks (about 30 days) are predicted
        for ($i = 8; $i < 12; $i++) {
            $days_ahead = (($i - 8) * 7) + 1;
            $chart_labels[] = date("M j", strtotime("+" . $days_ahead . " days"));
            $actual_data[] = null;
            $forecast_data[] = predict_next($weekly_units, $i);
        }

        // ---------------------------------------------------------
        // card 3: expected sales next week (same trend line as the chart, but for all sales)
        // ---------------------------------------------------------
        $weekly_sales = array(0, 0, 0, 0, 0, 0, 0, 0);
        foreach (Proc::call('sp_weekly_sales') as $row) {
            $position = 7 - (int) $row['weeks_ago'];
            if ($position >= 0 && $position <= 7) {
                $weekly_sales[$position] = (float) $row['total'];
            }
        }
        $expected_next_week = predict_next($weekly_sales, 8);

        // ---------------------------------------------------------
        // sales by category (top 4 and the rest as "Other")
        // ---------------------------------------------------------
        $cat_colors = array("#0d9488", "#2563eb", "#059669", "#d97706");
        $categories = array();
        $shown_percent = 0;
        for ($i = 0; $i < count($category_rows); $i++) {
            if ($i < 4) {
                $categories[] = array($category_rows[$i]['category_name'], $category_rows[$i]['percent'], $cat_colors[$i]);
                $shown_percent = $shown_percent + $category_rows[$i]['percent'];
            }
        }
        if (count($category_rows) > 4) {
            $other_percent = 100 - $shown_percent;
            if ($other_percent < 0) {
                $other_percent = 0;
            }
            $categories[] = array("Other", $other_percent, "#94a3b8");
        }

        // ---------------------------------------------------------
        // period comparison table
        // ---------------------------------------------------------
        if (count($compare_rows) > 0) {
            $cmp = $compare_rows[0];
        } else {
            $cmp = array("sales_now" => 0, "sales_prev" => 0, "orders_now" => 0, "orders_prev" => 0,
                         "units_now" => 0, "units_prev" => 0, "stockin_now" => 0, "stockin_prev" => 0);
        }

        if ($cmp['orders_now'] > 0) {
            $avg_now = $cmp['sales_now'] / $cmp['orders_now'];
        } else {
            $avg_now = 0;
        }
        if ($cmp['orders_prev'] > 0) {
            $avg_prev = $cmp['sales_prev'] / $cmp['orders_prev'];
        } else {
            $avg_prev = 0;
        }

        // name, this period, last period, value now, value before
        $compare_list = array(
            array("Total Sales", "₱ " . number_format($cmp['sales_now']), "₱ " . number_format($cmp['sales_prev']), $cmp['sales_now'], $cmp['sales_prev']),
            array("Transactions", number_format($cmp['orders_now']), number_format($cmp['orders_prev']), $cmp['orders_now'], $cmp['orders_prev']),
            array("Units Sold", number_format($cmp['units_now']), number_format($cmp['units_prev']), $cmp['units_now'], $cmp['units_prev']),
            array("Avg. Order Value", "₱ " . number_format($avg_now), "₱ " . number_format($avg_prev), $avg_now, $avg_prev),
            array("Stock-In Cost", "₱ " . number_format($cmp['stockin_now']), "₱ " . number_format($cmp['stockin_prev']), $cmp['stockin_now'], $cmp['stockin_prev'])
        );

        // ---------------------------------------------------------
        // inventory value summary
        // ---------------------------------------------------------
        $inventory_total = 0;
        $value_top = array();
        $value_other = 0;
        for ($i = 0; $i < count($value_rows); $i++) {
            $inventory_total = $inventory_total + $value_rows[$i]['total_value'];
            if ($i < 4) {
                $value_top[] = $value_rows[$i];
            } else {
                $value_other = $value_other + $value_rows[$i]['total_value'];
            }
        }

        // ---------------------------------------------------------
        // fast / slow / non-moving medicines
        // ---------------------------------------------------------
        $groups = array(
            "Fast-moving" => array(),
            "Slow-moving" => array(),
            "Non-moving" => array()
        );
        $dead_value = 0;
        foreach ($movement_rows as $row) {
            $groups[$row['movement']][] = $row;
            if ($row['movement'] == "Non-moving") {
                $dead_value = $dead_value + $row['stock_value'];
            }
        }

        // header settings
        $page_title = "Analytics";
        $page_subtitle = "Demand forecasting and sales trend intelligence";
        $current_page = "analytics";


        return view('analytics', compact('kpi', 'period', 'now_label', 'prev_label', 'compare_sub', 'best_rows', 'value_rows', 'best_name', 'best_units', 'forecast_name', 'growth_text', 'growth_class', 'running_out', 'expected_next_week', 'chart_labels', 'actual_data', 'forecast_data', 'categories', 'compare_list', 'inventory_total', 'value_top', 'value_other', 'groups', 'dead_value', 'page_title', 'page_subtitle', 'current_page'));
    }
}
