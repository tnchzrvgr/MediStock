<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Alerts page
class AlertController extends Controller
{
    public function index(Request $request)
    {
        // numbers for the 4 cards
        $kpi_rows = Proc::call('sp_alert_kpis');
        if (count($kpi_rows) == 0) {
            abort(500, "No alert data found. Please run dashboard.sql and then alerts.sql in phpMyAdmin first.");
        }
        $kpi = $kpi_rows[0];

        // how many days the Near-Expiry list looks ahead (30, 60, 90 or a number typed by the user)
        $days = 30;
        $wanted = $request->query('days');
        if ($wanted !== null && ctype_digit($wanted)) {
            $days = (int)$wanted;
        }
        if ($days < 1) {
            $days = 1;
        }
        if ($days > 365) {
            $days = 365;
        }
        $custom = ($days != 30 && $days != 60 && $days != 90);

        // the four lists (from the stored procedures)
        $low_list = Proc::call('sp_low_stock_alerts');
        $over_list = Proc::call('sp_overstock_alerts');
        $reorder_list = Proc::call('sp_reorder_suggestions');
        $expiry_list = Proc::call('sp_near_expiry', array($days));

        // header settings
        $page_title = "Alerts & Reorder";
        $page_subtitle = "Prevent overstocking and understocking before it costs you";
        $current_page = "alerts";


        return view('alerts', compact('kpi', 'days', 'custom', 'low_list', 'over_list', 'reorder_list', 'expiry_list', 'page_title', 'page_subtitle', 'current_page'));
    }
}
