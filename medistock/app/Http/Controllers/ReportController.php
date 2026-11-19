<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Reports page (6 reports, export to Excel and print)
class ReportController extends Controller
{
    public function index(Request $request)
    {
        // the 6 reports (key => name and short description)
        $report_list = array(
            "daily"     => array("Daily Sales Report", "Transactions and totals for a single day."),
            "weekly"    => array("Weekly Sales Report", "Sales summary grouped by day for the week."),
            "monthly"   => array("Monthly Sales Report", "Sales summary and comparison for the month."),
            "inventory" => array("Current Inventory Report", "All medicines with quantity, batch and value."),
            "stock"     => array("Low-Stock & Overstock Report", "Items below minimum or above maximum."),
            "stockin"   => array("Stock-In History Report", "All stock received, by supplier and batch.")
        );

        // which report is selected (only these values are allowed)
        $report = "daily";
        $wanted = $request->query('report');
        if (isset($report_list[$wanted])) {
            $report = $wanted;
        }

        // the date (must look like 2026-09-22, if not we use today)
        $date = date("Y-m-d");
        $wanted_date = $request->query('date', '');
        if (is_date($wanted_date)) {
            $date = $wanted_date;
        }

        // the staff (0 = all staff)
        $staff = (int) $request->query('staff', 0);

        $time = strtotime($date);
        $columns = array();
        $rows = array();
        $title = "";
        $footer_left = "";
        $footer_right = "";

        // ---------- get the data of the selected report (from the stored procedures) ----------
        if ($report == "daily") {
            $data = Proc::call('sp_report_daily', array($date, $staff));
            $title = "Daily Sales Report — " . date("M d, Y", $time);
            $columns = array("TIME", "ORDER", "ITEMS", "STAFF", "PAYMENT", "TOTAL");

            $total = 0;
            $units = 0;
            foreach ($data as $r) {
                $rows[] = array(date("g:i A", strtotime($r['sale_date'])), $r['order_no'], items_text($r['units_sold']),
                                $r['staff_name'], $r['payment_method'], money($r['total_amount']));
                $total = $total + $r['total_amount'];
                $units = $units + $r['units_sold'];
            }
            $footer_left = count($data) . " transactions · " . $units . " items sold";
            $footer_right = "Total Sales: " . money($total);

        } elseif ($report == "weekly" || $report == "monthly") {
            if ($report == "weekly") {
                $data = Proc::call('sp_report_weekly', array($date, $staff));
                $week_start = strtotime("-" . (date("N", $time) - 1) . " days", $time);
                $week_end = strtotime("+6 days", $week_start);
                $title = "Weekly Sales Report — " . date("M d", $week_start) . " – " . date("M d, Y", $week_end);
            } else {
                $data = Proc::call('sp_report_monthly', array($date, $staff));
                $title = "Monthly Sales Report — " . date("F Y", $time);
            }
            $columns = array("DAY", "ORDERS", "ITEMS SOLD", "TOTAL");

            $total = 0;
            $units = 0;
            $orders = 0;
            foreach ($data as $r) {
                $rows[] = array(date("D, M d", strtotime($r['sale_day'])), $r['orders'], $r['units'], money($r['total']));
                $total = $total + $r['total'];
                $units = $units + $r['units'];
                $orders = $orders + $r['orders'];
            }
            $footer_left = $orders . " transactions · " . $units . " items sold";

            // monthly: compare with the month before
            if ($report == "monthly") {
                $before = Proc::call('sp_report_month_total', array($date, $staff));
                $before_total = $before[0]['total'];
                if ($before_total > 0) {
                    $change = (($total - $before_total) / $before_total) * 100;
                    if ($change >= 0) {
                        $footer_left = $footer_left . " · ▲ " . number_format($change, 1) . "% vs last month";
                    } else {
                        $footer_left = $footer_left . " · ▼ " . number_format(abs($change), 1) . "% vs last month";
                    }
                }
            }
            $footer_right = "Total Sales: " . money($total);

        } elseif ($report == "inventory") {
            $data = Proc::call('sp_report_inventory');
            $title = "Current Inventory Report — " . date("M d, Y");
            $columns = array("MEDICINE", "CATEGORY", "BATCH NO.", "STOCK QTY", "UNIT PRICE", "VALUE", "STATUS");

            $total = 0;
            foreach ($data as $r) {
                $value = $r['quantity'] * $r['unit_price'];
                $rows[] = array($r['medicine_name'], $r['category_name'], $r['batch_no'], number_format($r['quantity']) . " " . $r['unit'],
                                money($r['unit_price']), money($value), $r['status']);
                $total = $total + $value;
            }
            $footer_left = count($data) . " medicines";
            $footer_right = "Total Value: " . money($total);

        } elseif ($report == "stock") {
            $data = Proc::call('sp_report_stock_levels');
            $title = "Low-Stock & Overstock Report — " . date("M d, Y");
            $columns = array("MEDICINE", "CATEGORY", "STOCK QTY", "MINIMUM", "MAXIMUM", "STATUS");

            $low = 0;
            $over = 0;
            foreach ($data as $r) {
                $rows[] = array($r['medicine_name'], $r['category_name'], number_format($r['quantity']) . " " . $r['unit'],
                                number_format($r['reorder_level']), number_format($r['max_stock']), $r['status']);
                if ($r['status'] == "Overstock") {
                    $over++;
                } else {
                    $low++;
                }
            }
            $footer_left = count($data) . " items need attention";
            $footer_right = $low . " low or out of stock · " . $over . " overstock";

        } else {
            $data = Proc::call('sp_report_stock_in', array($date));
            $title = "Stock-In History Report — " . date("F Y", $time);
            $columns = array("DATE", "MEDICINE", "SUPPLIER", "BATCH NO.", "QUANTITY", "UNIT COST", "TOTAL COST");

            $total = 0;
            $units = 0;
            foreach ($data as $r) {
                $cost = $r['quantity'] * $r['unit_cost'];
                $supplier = $r['supplier_name'];
                if ($supplier == "") {
                    $supplier = "—";
                }
                $rows[] = array(date("M d, Y", strtotime($r['date_received'])), $r['medicine_name'], $supplier, $r['batch_no'],
                                number_format($r['quantity']), money($r['unit_cost']), money($cost));
                $total = $total + $cost;
                $units = $units + $r['quantity'];
            }
            $footer_left = count($data) . " deliveries · " . number_format($units) . " units received";
            $footer_right = "Total Cost: " . money($total);
        }

        // ---------- Export Excel (a CSV file that Excel opens) ----------
        if ($request->query('export') == "csv") {
            $file_name = $report . "_report_" . $date . ".csv";

            return response()->streamDownload(function () use ($title, $columns, $rows, $footer_left, $footer_right) {
                echo "\xEF\xBB\xBF";                      // so Excel shows ₱ correctly
                $out = fopen("php://output", "w");
                fputcsv($out, array($title));
                fputcsv($out, $columns);
                foreach ($rows as $r) {
                    fputcsv($out, $r);
                }
                fputcsv($out, array($footer_left, $footer_right));
                fclose($out);
            }, $file_name, array("Content-Type" => "text/csv; charset=utf-8"));
        }

        // staff for the drop-down
        $staff_list = Proc::call('sp_report_staff');

        // the link that keeps the date and staff when another report is clicked
        $keep = "&date=" . $date . "&staff=" . $staff;

        // header settings
        $page_title = "Reports";
        $page_subtitle = "Generate, export and print sales and inventory reports";
        $current_page = "reports";

        return view('reports', compact('report_list', 'report', 'date', 'staff', 'staff_list', 'title', 'columns', 'rows', 'footer_left', 'footer_right', 'keep', 'page_title', 'page_subtitle', 'current_page'));

    }
}
