<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Inventory page
class InventoryController extends Controller
{
    public function index(Request $request)
    {
        // which filter button is selected (only these values are allowed)
        $filter = "all";
        $wanted = $request->query('filter');
        if ($wanted == "low" || $wanted == "expiring" || $wanted == "out") {
            $filter = $wanted;
        }

        // what the user typed in the search box
        $search = trim($request->query('q', ''));

        // numbers for the 4 cards
        $kpi_rows = Proc::call('sp_inventory_kpis');
        if (count($kpi_rows) == 0) {
            abort(500, "No inventory data found. Please run dashboard.sql and then inventory.sql in phpMyAdmin first.");
        }
        $kpi = $kpi_rows[0];

        // stock adjustment requests waiting for the owner (shown as a notice)
        $pending_count = 0;
        if (is_admin()) {
            $pending_count = count(Proc::call('sp_pending_adjustments'));
        }

        // the medicine list (filter + search are sent to the stored procedure)
        $medicines = Proc::call('sp_inventory_list', array($filter, $search));

        // header settings
        $page_title = "Inventory";
        $page_subtitle = "Manage medicines, stock levels and batches across your pharmacy";
        $current_page = "inventory";

        // show the search box in the header
        $show_search = true;
        $search_action = route('inventory');
        $search_value = $search;
        $search_filter = $filter;

        // keeps the search text when a filter button is clicked
        $search_link = "";
        if ($search != "") {
            $search_link = "&q=" . urlencode($search);
        }


        return view('inventory', compact('filter', 'kpi', 'pending_count', 'medicines', 'page_title', 'page_subtitle', 'current_page', 'show_search', 'search_action', 'search_value', 'search_filter', 'search_link'));
    }
}
