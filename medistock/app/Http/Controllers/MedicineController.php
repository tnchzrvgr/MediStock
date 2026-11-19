<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Add / Edit medicine page
class MedicineController extends Controller
{
    // show the form (empty for a new medicine, or filled when the link has ?id=)
    public function form(Request $request)
    {
        $medicine_id = (int) $request->query('id', 0);

        // starting values of the form (empty when adding a new medicine)
        $form = array(
            "medicine_name" => "",
            "generic_name"  => "",
            "category_id"   => "",
            "unit"          => "Tablet",
            "unit_price"    => "",
            "quantity"      => "",
            "reorder_level" => "",
            "max_stock"     => ""
        );

        // a new medicine starts with the default minimum and maximum from the Settings page
        if ($medicine_id == 0) {
            try {
                $settings = Proc::call('sp_get_settings');
                if (count($settings) > 0) {
                    $form['reorder_level'] = $settings[0]['default_min'];
                    $form['max_stock'] = $settings[0]['default_max'];
                }
            } catch (\Exception $e) {
                // settings.sql was not run yet, the boxes stay empty
            }
        }

        // editing: load the medicine from the database
        if ($medicine_id > 0) {
            $found = Proc::call('sp_get_medicine', array($medicine_id));
            if (count($found) == 0) {
                return redirect()->route('inventory')->with('flash', array("type" => "error", "text" => "Medicine not found."));
            }
            $form = $found[0];
        }

        $field_errors = array();
        $duplicate = false;

        return $this->showForm($medicine_id, $form, $field_errors, $duplicate);
    }

    // the Save and Archive buttons
    public function save(Request $request)
    {
        $medicine_id = (int) $request->input('medicine_id', 0);

        // ----- archive button -----
        if ($request->input('action') == "archive" && $medicine_id > 0) {
            Proc::call('sp_archive_medicine', array($medicine_id));
            return redirect()->route('inventory')->with('flash', array("type" => "success", "text" => "Medicine archived. Its history is kept."));
        }

        // ----- save button -----
        $form = array();
        $form['medicine_name'] = trim($request->input('medicine_name', ''));
        $form['generic_name']  = trim($request->input('generic_name', ''));
        $form['category_id']   = $request->input('category_id', '');
        $form['unit']          = $request->input('unit', 'Tablet');
        $form['unit_price']    = trim($request->input('unit_price', ''));
        $form['quantity']      = trim($request->input('quantity', ''));
        $form['reorder_level'] = trim($request->input('reorder_level', ''));
        $form['max_stock']     = trim($request->input('max_stock', ''));

        // check the fields
        $field_errors = array();
        if ($form['medicine_name'] == "") {
            $field_errors['medicine_name'] = "Medicine name is required.";
        }
        if ($form['generic_name'] == "") {
            $field_errors['generic_name'] = "Generic name is required.";
        }
        if ($form['category_id'] == "") {
            $field_errors['category_id'] = "Please choose a category.";
        }
        if (!is_numeric($form['unit_price']) || $form['unit_price'] <= 0) {
            $field_errors['unit_price'] = "Enter a price greater than 0.";
        }
        if (!ctype_digit($form['quantity'])) {
            $field_errors['quantity'] = "Enter a whole number (0 or more).";
        }
        if (!ctype_digit($form['reorder_level'])) {
            $field_errors['reorder_level'] = "Enter a whole number (0 or more).";
        }
        if (!ctype_digit($form['max_stock'])) {
            $field_errors['max_stock'] = "Enter a whole number.";
        } elseif (ctype_digit($form['reorder_level']) && (int) $form['max_stock'] <= (int) $form['reorder_level']) {
            $field_errors['max_stock'] = "Maximum must be higher than the minimum.";
        }

        $duplicate = false;

        // no errors: save with the stored procedure
        if (count($field_errors) == 0) {
            if ($medicine_id > 0) {
                $answer = Proc::call('sp_update_medicine', array(
                    $medicine_id, $form['medicine_name'], $form['generic_name'], $form['category_id'],
                    $form['unit'], $form['unit_price'], $form['quantity'], $form['reorder_level'], $form['max_stock']
                ));
            } else {
                $answer = Proc::call('sp_add_medicine', array(
                    $form['medicine_name'], $form['generic_name'], $form['category_id'],
                    $form['unit'], $form['unit_price'], $form['quantity'], $form['reorder_level'], $form['max_stock']
                ));
            }

            if (count($answer) > 0 && $answer[0]['result'] == "ok") {
                if ($medicine_id > 0) {
                    $text = "Medicine updated.";
                } else {
                    $text = "Medicine added to the inventory.";
                }
                return redirect()->route('inventory')->with('flash', array("type" => "success", "text" => $text));
            }

            $duplicate = true;
        }

        // something is wrong: show the form again with the messages
        return $this->showForm($medicine_id, $form, $field_errors, $duplicate);
    }

    // builds the page (used by form and save)
    private function showForm($medicine_id, $form, $field_errors, $duplicate)
    {
        // the units that can be picked
        $units = array("Tablet", "Capsule", "Bottle", "Vial", "Box", "Tube", "Sachet", "units");

        // categories for the drop-down list
        $categories = Proc::call('sp_list_categories');

        // stock status preview (the script at the bottom updates it while typing)
        $preview_qty = (int) $form['quantity'];
        $preview_min = (int) $form['reorder_level'];
        $preview_max = (int) $form['max_stock'];

        if ($preview_qty == 0 && $form['quantity'] === "") {
            $preview_text = "No quantity yet";
            $preview_label = "—";
            $preview_class = "preview-normal";
        } elseif ($preview_qty == 0) {
            $preview_label = "OUT";
            $preview_class = "preview-low";
        } elseif ($preview_qty <= $preview_min) {
            $preview_label = "LOW";
            $preview_class = "preview-low";
        } elseif ($preview_max > 0 && $preview_qty > $preview_max) {
            $preview_label = "OVERSTOCK";
            $preview_class = "preview-over";
        } else {
            $preview_label = "NORMAL";
            $preview_class = "preview-normal";
        }
        if (!isset($preview_text)) {
            $preview_text = "Current: " . $preview_qty . " units (min " . $preview_min . " · max " . $preview_max . ")";
        }

        // header settings
        $page_title = "Add / Edit Medicine";
        $page_subtitle = "Inventory › Medicine record";
        $current_page = "inventory";

        return view('add_medicine', compact('medicine_id', 'form', 'field_errors', 'duplicate', 'units', 'categories',
            'preview_text', 'preview_label', 'preview_class', 'page_title', 'page_subtitle', 'current_page'));
    }

    // ---------- Medicine details page (the View link in Inventory) ----------

    public function show(Request $request, $id)
    {
        $found = Proc::call('sp_medicine_detail', array((int) $id));
        if (count($found) == 0) {
            return redirect()->route('inventory')->with('flash', array("type" => "error", "text" => "Medicine not found."));
        }
        $medicine = $found[0];

        // the near-expiry window from the Settings page (30 days if the page was not set up yet)
        $window = 30;
        try {
            $settings = Proc::call('sp_get_settings');
            if (count($settings) > 0) {
                $window = (int) $settings[0]['expiry_days'];
            }
        } catch (\Exception $e) {
            // settings.sql was not run yet
        }

        // stock status (the card on the right)
        $qty = $medicine['quantity'];
        if ($qty == 0) {
            $status_text = "Out of stock";
            $status_class = "pill-danger";
        } elseif ($qty <= $medicine['reorder_level']) {
            $status_text = "Low — below minimum (" . $medicine['reorder_level'] . ")";
            $status_class = "pill-danger";
        } elseif ($qty > $medicine['max_stock']) {
            $status_text = "Overstock — above maximum (" . $medicine['max_stock'] . ")";
            $status_class = "pill-amber";
        } else {
            $status_text = "Normal";
            $status_class = "pill-up";
        }

        // batches: the stock on hand is shared between the batches, the newest batch first
        // (the oldest stock is sold first, so the old batches are the ones that become 0)
        $batch_rows = Proc::call('sp_medicine_batches', array((int) $id));
        if (count($batch_rows) == 0 && $medicine['batch_no'] != "") {
            // no stock-in record: show the batch that is saved with the medicine
            $batch_rows = array(array(
                "batch_no" => $medicine['batch_no'],
                "date_received" => $medicine['date_added'],
                "expiry_date" => $medicine['expiry_date'],
                "quantity" => $qty
            ));
        }

        $left = $qty;
        $batches = array();
        foreach ($batch_rows as $row) {
            $remaining = min($left, $row['quantity']);
            $left = $left - $remaining;

            // status text of the batch
            $days = null;
            if ($row['expiry_date'] != "") {
                $days = (strtotime($row['expiry_date']) - strtotime(date("Y-m-d"))) / 86400;
            }

            if ($remaining == 0) {
                $text = "Sold out";
                $color = "status-gray";
            } elseif ($days !== null && $days < 0) {
                $text = "Expired";
                $color = "status-red";
            } elseif ($days !== null && $days <= $window) {
                $text = "Within " . $window . " days";
                $color = "status-amber";
            } else {
                $text = "Good";
                $color = "status-green";
            }

            $batches[] = array(
                "batch_no" => $row['batch_no'],
                "received" => $row['date_received'] == "" ? "—" : date("M d, Y", strtotime($row['date_received'])),
                "expiry" => $row['expiry_date'] == "" ? "—" : date("M d, Y", strtotime($row['expiry_date'])),
                "remaining" => $remaining,
                "text" => $text,
                "color" => $color
            );
        }

        // stock movement history
        $movements = array();
        foreach (Proc::call('sp_medicine_movements', array((int) $id)) as $row) {
            // a stock-in only has a date (no time)
            if (date("H:i:s", strtotime($row['move_time'])) == "00:00:00") {
                $when = date("M j", strtotime($row['move_time']));
            } else {
                $when = date("M j, g:i A", strtotime($row['move_time']));
            }
            $movements[] = array(
                "when" => $when,
                "type" => $row['move_type'],
                "qty" => $row['qty_change'],
                "by" => $row['by_name']
            );
        }

        // header settings
        $page_title = $medicine['medicine_name'];
        $page_subtitle = "Inventory › Medicine details";
        $current_page = "inventory";

        return view('medicine_view', compact('medicine', 'status_text', 'status_class', 'batches', 'movements',
            'page_title', 'page_subtitle', 'current_page'));
    }
}
