<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Sales page
class SaleController extends Controller
{
    public function index(Request $request)
    {
        // get the data from the stored procedures
        $kpi_rows = Proc::call('sp_sales_kpis');
        $week_rows = Proc::call('sp_sales_this_week');
        $payment_rows = Proc::call('sp_payment_methods');
        $sales_rows = Proc::call('sp_recent_transactions');

        if (count($kpi_rows) == 0) {
            abort(500, "No sales data found. Please run dashboard.sql and then sales.sql in phpMyAdmin first.");
        }
        $kpi = $kpi_rows[0];

        // the pills under the card numbers (in plain words)
        $today_class = "pill-blue";
        $today_text = $kpi['transactions_today'] . ($kpi['transactions_today'] == 1 ? " transaction today" : " transactions today");
        list($week_class, $week_text) = change_words($kpi['week_sales'], $kpi['last_week_sales'], "last week");
        list($month_class, $month_text) = change_words($kpi['month_sales'], $kpi['last_month_sales'], "last month");
        list($units_class, $units_text) = change_words($kpi['units_today'], $kpi['units_yesterday'], "yesterday", false);

        // data for the bar chart (Monday to Sunday of this week)
        $days_since_monday = date("N") - 1;
        $week_start = strtotime("-" . $days_since_monday . " days");

        $week_totals = array();
        foreach ($week_rows as $row) {
            $week_totals[$row['sale_day']] = (float) $row['daily_total'];
        }

        $day_names = array("Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun");
        $day_values = array();
        for ($i = 0; $i < 7; $i++) {
            $day = date("Y-m-d", strtotime("+" . $i . " days", $week_start));
            if (isset($week_totals[$day])) {
                $day_values[] = $week_totals[$day];
            } else {
                $day_values[] = 0;
            }
        }

        // names and colors for the payment methods list
        $pay_names = array(
            "Cash" => "Cash",
            "GCash" => "GCash"
        );
        $pay_colors = array(
            "Cash" => "#059669",
            "GCash" => "#2563eb"
        );

        // header settings
        $page_title = "Sales";
        $page_subtitle = "Track transactions, revenue and payment performance";
        $current_page = "sales";


        return view('sales', compact('payment_rows', 'sales_rows', 'kpi', 'day_names', 'day_values', 'pay_names', 'pay_colors', 'page_title', 'page_subtitle', 'current_page', 'today_class', 'today_text', 'week_class', 'week_text', 'month_class', 'month_text', 'units_class', 'units_text'));
    }

    // ---------- New Sale page ----------

    // all medicines that can be sold, the array key is the medicine id
    private function getMedicines()
    {
        $medicines = array();
        $list = Proc::call('sp_sale_medicines');
        foreach ($list as $m) {
            $medicines[$m['medicine_id']] = $m;
        }
        return $medicines;
    }

    // small helper: save a message and go back to the New Sale page
    private function goBack($type, $text)
    {
        return redirect()->route('sales.new')->with('flash', array("type" => $type, "text" => $text));
    }

    public function newSale(Request $request)
    {
        // the cart is kept in the session: medicine_id => quantity
        $cart = session('cart', array());
        $medicines = $this->getMedicines();

        // the search text and the medicine that was clicked in the results
        $q = trim($request->query('q', ''));
        $pick = (int) $request->query('pick', 0);

        // ---------- search results ----------
        $results = array();
        if ($q != "") {
            foreach ($medicines as $m) {
                if (stripos($m['medicine_name'], $q) !== false) {
                    $results[] = $m;
                }
            }
            $results = array_slice($results, 0, 6);

            // only one match: select it automatically
            if ($pick == 0 && count($results) == 1) {
                $pick = $results[0]['medicine_id'];
            }
        }

        // ---------- cart rows and totals ----------
        $cart_rows = array();
        $total = 0;
        $total_units = 0;
        $problems = array();

        foreach ($cart as $id => $qty) {
            if (!isset($medicines[$id])) {
                continue;
            }
            $m = $medicines[$id];
            $subtotal = $qty * $m['unit_price'];

            $too_many = false;
            if ($m['is_expired'] == 1) {
                $too_many = true;
                $problems[] = $m['medicine_name'] . " is expired (" . date("M d, Y", strtotime($m['expiry_date'])) . ") and cannot be sold. Remove it from the cart.";
            } elseif ($qty > $m['quantity']) {
                $too_many = true;
                $problems[] = $m['medicine_name'] . ": only " . $m['quantity'] . " units in stock. Reduce the quantity to continue — sales above available stock are blocked.";
            }

            $cart_rows[] = array(
                "id" => $id,
                "name" => $m['medicine_name'],
                "qty" => $qty,
                "price" => $m['unit_price'],
                "subtotal" => $subtotal,
                "too_many" => $too_many
            );
            $total = $total + $subtotal;
            $total_units = $total_units + $qty;
        }

        $can_complete = (count($cart_rows) > 0 && count($problems) == 0);

        // header settings
        $page_title = "New Sale";
        $page_subtitle = "Search a medicine, enter quantity — totals are calculated automatically";
        $current_page = "sales";

        return view('new_sale', compact('medicines', 'q', 'pick', 'results', 'cart_rows', 'total', 'total_units',
            'problems', 'can_complete', 'page_title', 'page_subtitle', 'current_page'));
    }

    // the buttons: Add to Cart, remove (X) and Complete Sale
    public function newSaleAction(Request $request)
    {
        $action = $request->input('action');
        $cart = session('cart', array());
        $medicines = $this->getMedicines();

        // ----- Add to Cart -----
        if ($action == "add") {
            $id = (int) $request->input('medicine_id');
            $qty = (int) $request->input('quantity');

            if (!isset($medicines[$id])) {
                return $this->goBack("error", "Please click a medicine in the search results first.");
            }
            if ($qty < 1) {
                return $this->goBack("error", "Quantity must be at least 1.");
            }

            // expired medicines are never sold
            if ($medicines[$id]['is_expired'] == 1) {
                return $this->goBack("error", $medicines[$id]['medicine_name'] . " is expired (" . date("M d, Y", strtotime($medicines[$id]['expiry_date'])) . ") and cannot be sold. Ask the owner to remove the expired stock.");
            }

            $already = 0;
            if (isset($cart[$id])) {
                $already = $cart[$id];
            }

            // sales above the available stock are blocked
            if ($already + $qty > $medicines[$id]['quantity']) {
                return $this->goBack("error", $medicines[$id]['medicine_name'] . ": only " . $medicines[$id]['quantity'] . " units in stock. Sales above available stock are blocked.");
            }

            $cart[$id] = $already + $qty;
            session(['cart' => $cart]);
            return $this->goBack("success", $medicines[$id]['medicine_name'] . " added to the cart.");
        }

        // ----- remove one item (the X) -----
        if ($action == "remove") {
            $id = (int) $request->input('medicine_id');
            unset($cart[$id]);
            session(['cart' => $cart]);
            return redirect()->route('sales.new');
        }

        // ----- Complete Sale -----
        if ($action == "complete") {
            $payment = $request->input('payment_method');
            if ($payment != "Cash" && $payment != "GCash") {
                $payment = "Cash";
            }

            if (count($cart) == 0) {
                return $this->goBack("error", "The cart is empty.");
            }

            // work out the total again on the server (the prices come from the database)
            $total = 0;
            foreach ($cart as $id => $qty) {
                if (!isset($medicines[$id])) {
                    unset($cart[$id]);
                    session(['cart' => $cart]);
                    return $this->goBack("error", "A medicine in the cart is no longer available. Please check the cart.");
                }
                if ($medicines[$id]['is_expired'] == 1) {
                    return $this->goBack("error", $medicines[$id]['medicine_name'] . " is expired and cannot be sold. Remove it from the cart.");
                }
                if ($qty > $medicines[$id]['quantity']) {
                    return $this->goBack("error", $medicines[$id]['medicine_name'] . ": only " . $medicines[$id]['quantity'] . " units in stock. Reduce the quantity to continue.");
                }
                $total = $total + ($qty * $medicines[$id]['unit_price']);
            }

            // only cash needs an amount received
            if ($payment == "Cash") {
                $received = (float) $request->input('amount_received');
                if ($received < $total) {
                    return $this->goBack("error", "The amount received is less than the total.");
                }
            } else {
                $received = $total;
            }

            // save everything, or nothing (transaction)
            DB::beginTransaction();
            $all_ok = true;
            $sale_id = 0;
            $order_no = "";

            $sale = Proc::call('sp_create_sale', array(session('user_id'), $payment, $received));

            if (count($sale) == 0) {
                $all_ok = false;
            } else {
                $sale_id = (int) $sale[0]['sale_id'];
                $order_no = $sale[0]['order_no'];

                foreach ($cart as $id => $qty) {
                    $result = Proc::call('sp_add_sale_item', array($sale_id, (int) $id, (int) $qty));
                    if (count($result) == 0 || $result[0]['result'] != "ok") {
                        $all_ok = false;
                        break;
                    }
                }
            }

            if ($all_ok) {
                Proc::call('sp_finish_sale', array($sale_id));
                DB::commit();
                session(['cart' => array()]);
                // show the receipt of this sale
                $text = "✓ Sale " . $order_no . " completed. Stock has been updated automatically.";
                return redirect()->route('receipt', array('id' => $sale_id, 'just' => 1))->with('flash', array("type" => "success", "text" => $text));
            }

            DB::rollBack();
            return $this->goBack("error", "The sale could not be completed because the stock changed. Please check the cart and try again.");
        }

        return redirect()->route('sales.new');
    }
}
