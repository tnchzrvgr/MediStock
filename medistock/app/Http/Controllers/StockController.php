<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Stock-In and Stock Adjustment pages
class StockController extends Controller
{
    // ---------- Stock-In ----------

    public function stockIn(Request $request)
    {
        // starting values of the form
        $form = array(
            "supplier"    => "",
            "medicine_id" => (int) $request->query('id', 0),
            "quantity"    => "",
            "batch_no"    => "",
            "expiry_date" => "",
            "received"    => date("Y-m-d")
        );

        return $this->showStockIn($form, array());
    }

    public function stockInSave(Request $request)
    {
        $form = array();
        $form['supplier']    = trim($request->input('supplier', ''));
        $form['medicine_id'] = (int) $request->input('medicine_id', 0);
        $form['quantity']    = trim($request->input('quantity', ''));
        $form['batch_no']    = trim($request->input('batch_no', ''));
        $form['expiry_date'] = trim($request->input('expiry_date', ''));
        $form['received']    = trim($request->input('received', ''));

        // check the fields
        $field_errors = array();
        if ($form['supplier'] == "") {
            $field_errors['supplier'] = "Please choose a supplier.";
        } else {
            // only Active suppliers are allowed (On Hold and Inactive are not in the drop-down)
            $allowed = false;
            foreach (Proc::call('sp_stock_in_suppliers') as $row) {
                if ($row['supplier_name'] == $form['supplier']) {
                    $allowed = true;
                }
            }
            if (!$allowed) {
                $field_errors['supplier'] = "This supplier is not active. Please choose another one.";
            }
        }
        if ($form['medicine_id'] == 0) {
            $field_errors['medicine_id'] = "Please choose a medicine.";
        }
        if (!ctype_digit($form['quantity']) || (int) $form['quantity'] < 1) {
            $field_errors['quantity'] = "Enter a whole number of 1 or more.";
        }
        if ($form['batch_no'] == "") {
            $field_errors['batch_no'] = "Batch number is required.";
        } elseif (strlen($form['batch_no']) > 20) {
            $field_errors['batch_no'] = "Use 20 characters or less.";
        }
        if (!is_date($form['received'])) {
            $field_errors['received'] = "Choose the date received.";
        } elseif ($form['received'] > date("Y-m-d")) {
            $field_errors['received'] = "The date received cannot be in the future.";
        }
        if (!is_date($form['expiry_date'])) {
            $field_errors['expiry_date'] = "Choose the expiration date.";
        } elseif (!isset($field_errors['received']) && $form['expiry_date'] <= $form['received']) {
            $field_errors['expiry_date'] = "The expiration date must be after the date received.";
        }

        // no errors: save with the stored procedure
        if (count($field_errors) == 0) {
            $answer = Proc::call('sp_record_stock_in', array(
                $form['medicine_id'], $form['supplier'], (int) $form['quantity'],
                $form['batch_no'], $form['expiry_date'], $form['received'], session('user_id')
            ));

            if (count($answer) > 0 && $answer[0]['result'] == "ok") {
                $text = "Stock-in recorded. New stock: " . number_format($answer[0]['new_quantity']) . " units.";
                return redirect()->route('stock.in')->with('flash', array("type" => "success", "text" => $text));
            }

            $field_errors['medicine_id'] = "This medicine was not found. Please choose again.";
        }

        return $this->showStockIn($form, $field_errors);
    }

    private function showStockIn($form, $field_errors)
    {
        // drop-down lists and the history table (from the stored procedures)
        $medicines = Proc::call('sp_stock_in_medicines');
        $suppliers = Proc::call('sp_stock_in_suppliers');
        $history = Proc::call('sp_recent_stock_in');

        // header settings
        $page_title = "Stock-In";
        $page_subtitle = "Record stock received from a supplier";
        $current_page = "inventory";

        return view('stock_in', compact('medicines', 'suppliers', 'history', 'form', 'field_errors',
            'page_title', 'page_subtitle', 'current_page'));
    }

    // ---------- Remove Expired Stock ----------

    public function expired()
    {
        return $this->showExpired(array());
    }

    // makes one "Expired" adjustment for every medicine that was checked
    // (the owner's adjustment is saved at once, a staff member's goes to the owner for approval)
    public function expiredSave(Request $request)
    {
        $ids = (array) $request->input('pick', array());
        $quantities = (array) $request->input('qty', array());

        // only medicines that are really in the expired list can be used
        $list = array();
        foreach (Proc::call('sp_expired_medicines') as $row) {
            $list[$row['medicine_id']] = $row;
        }

        $done = 0;
        $problems = array();
        foreach ($ids as $id) {
            $id = (int) $id;
            if (!isset($list[$id])) {
                continue;
            }
            $row = $list[$id];
            $qty = isset($quantities[$id]) ? trim($quantities[$id]) : '';

            if (!ctype_digit($qty) || (int) $qty < 1 || (int) $qty > $row['quantity']) {
                $problems[$id] = "Enter 1 to " . $row['quantity'] . ".";
                continue;
            }

            $answer = Proc::call('sp_record_adjustment', array(
                $id, -((int) $qty), "Expired", "Expired on " . date("M d, Y", strtotime($row['expiry_date'])), session('user_id'), is_admin() ? 1 : 0
            ));
            if (count($answer) > 0 && ($answer[0]['result'] == "ok" || $answer[0]['result'] == "requested")) {
                $done++;
            }
        }

        if (count($ids) == 0) {
            return $this->showExpired(array("_all" => "Please check at least one medicine."));
        }
        if (count($problems) > 0) {
            return $this->showExpired($problems);
        }

        if (is_admin()) {
            $text = $done . " expired medicine(s) removed from stock and logged.";
        } else {
            $text = $done . " removal request(s) sent. The stock will change after the owner approves.";
        }
        return redirect()->route('stock.adjust')->with('flash', array("type" => "success", "text" => $text));
    }

    private function showExpired($field_errors)
    {
        $expired = Proc::call('sp_expired_medicines');

        $page_title = "Remove Expired Stock";
        $page_subtitle = is_admin() ? "Take expired medicines out of the stock" : "Send expired medicines to the owner for removal";
        $current_page = "inventory";

        return view('stock_expired', compact('expired', 'field_errors', 'page_title', 'page_subtitle', 'current_page'));
    }

    // ---------- Stock Adjustment ----------

    public function adjust(Request $request)
    {
        // starting values of the form
        $form = array("medicine_id" => (int) $request->query('id', 0), "quantity" => "", "reason" => "", "notes" => "");

        return $this->showAdjust($form, array());
    }

    public function adjustSave(Request $request)
    {
        $medicines = Proc::call('sp_adjust_medicines');
        $reasons = array("Damaged", "Expired", "Count correction");

        $form = array();
        $form['medicine_id'] = (int) $request->input('medicine_id', 0);
        $form['quantity']    = trim($request->input('quantity', ''));
        $form['reason']      = $request->input('reason', '');
        $form['notes']       = trim($request->input('notes', ''));

        // find the chosen medicine
        $chosen = null;
        foreach ($medicines as $m) {
            if ($m['medicine_id'] == $form['medicine_id']) {
                $chosen = $m;
            }
        }

        $field_errors = array();
        if ($chosen == null) {
            $field_errors['medicine_id'] = "Please choose a medicine.";
        }

        // a reason is required
        if (!in_array($form['reason'], $reasons)) {
            $field_errors['reason'] = "Please choose a reason. Adjustments cannot be saved without a reason.";
        }

        // the change: Damaged / Expired remove stock, Count correction can add (+) or remove (-)
        $change = 0;
        if (!preg_match('/^-?\d+$/', $form['quantity']) || (int) $form['quantity'] == 0) {
            $field_errors['quantity'] = "Enter a whole number that is not 0.";
        } elseif ($form['reason'] == "Count correction") {
            $change = (int) $form['quantity'];
        } elseif ((int) $form['quantity'] < 0) {
            $field_errors['quantity'] = "Enter the number of units to remove (1 or more).";
        } else {
            $change = -(int) $form['quantity'];
        }

        if (count($field_errors) == 0 && $chosen['quantity'] + $change < 0) {
            $field_errors['quantity'] = "You cannot remove more than the current stock (" . $chosen['quantity'] . ").";
        }

        if (strlen($form['notes']) > 200) {
            $field_errors['notes'] = "Use 200 characters or less.";
        }

        // no errors: save with the stored procedure
        if (count($field_errors) == 0) {
            $answer = Proc::call('sp_record_adjustment', array(
                $form['medicine_id'], $change, $form['reason'], $form['notes'], session('user_id'), is_admin() ? 1 : 0
            ));

            if (count($answer) > 0 && $answer[0]['result'] == "ok") {
                return redirect()->route('stock.adjust')->with('flash', array("type" => "success", "text" => "Stock adjustment saved and logged."));
            }
            if (count($answer) > 0 && $answer[0]['result'] == "requested") {
                return redirect()->route('stock.adjust')->with('flash', array("type" => "success", "text" => "Request sent. The stock will change after the owner approves it."));
            }

            $field_errors['quantity'] = "The adjustment could not be saved. Please check the quantity.";
        }

        return $this->showAdjust($form, $field_errors);
    }

    // the owner approves or rejects a request (the buttons in the "Waiting for approval" table)
    public function adjustDecide(Request $request)
    {
        $id = (int) $request->input('adjust_id', 0);
        $approve = $request->input('decision') == "approve" ? 1 : 0;

        $answer = Proc::call('sp_decide_adjustment', array($id, $approve, session('user_id')));
        $result = count($answer) > 0 ? $answer[0]['result'] : "";

        if ($result == "approved") {
            $flash = array("type" => "success", "text" => "Request approved. The stock was updated and logged.");
        } elseif ($result == "rejected") {
            $flash = array("type" => "success", "text" => "Request rejected. The stock was not changed.");
        } elseif ($result == "below zero") {
            $flash = array("type" => "error", "text" => "This request cannot be approved because the stock is now too low. Reject it instead.");
        } else {
            $flash = array("type" => "error", "text" => "This request was not found or was already decided.");
        }

        return redirect()->route('stock.adjust')->with('flash', $flash);
    }

    private function showAdjust($form, $field_errors)
    {
        // drop-down list and the log table (from the stored procedures)
        $medicines = Proc::call('sp_adjust_medicines');
        $log = Proc::call('sp_adjustment_log');
        $pending = Proc::call('sp_pending_adjustments');

        // the reasons that can be picked
        $reasons = array("Damaged", "Expired", "Count correction");

        // header settings
        if (is_admin()) {
            $page_title = "Stock Adjustment";
            $page_subtitle = "Correct stock with a required reason — every change is logged";
        } else {
            $page_title = "Request Stock Adjustment";
            $page_subtitle = "Send a correction to the owner for approval — the stock changes after it is approved";
        }
        $current_page = "inventory";

        return view('stock_adjust', compact('medicines', 'log', 'pending', 'reasons', 'form', 'field_errors',
            'page_title', 'page_subtitle', 'current_page'));
    }
}
