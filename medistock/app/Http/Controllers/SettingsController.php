<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Settings page (only the Owner / Admin, see the "admin" middleware)
class SettingsController extends Controller
{
    // show the page with the saved settings
    public function index(Request $request)
    {
        $rows = Proc::call('sp_get_settings');
        if (count($rows) == 0) {
            abort(500, "No settings found. Please run settings.sql in phpMyAdmin first.");
        }

        return $this->showForm($rows[0], array());
    }

    // Save Changes button
    public function save(Request $request)
    {
        $form = array();
        $form['pharmacy_name']  = trim($request->input('pharmacy_name', ''));
        $form['address']        = trim($request->input('address', ''));
        $form['contact_number'] = trim($request->input('contact_number', ''));
        $form['email']          = trim($request->input('email', ''));
        $form['receipt_header'] = trim($request->input('receipt_header', ''));
        $form['receipt_footer'] = trim($request->input('receipt_footer', ''));
        $form['print_receipt']  = $request->has('print_receipt') ? 1 : 0;
        $form['default_min']    = trim($request->input('default_min', ''));
        $form['default_max']    = trim($request->input('default_max', ''));

        // near-expiry window: 30, 60, 90 or custom (the number typed in the box)
        $choice = $request->input('expiry_choice', '30');
        if ($choice == "custom") {
            $form['expiry_days'] = trim($request->input('custom_days', ''));
        } else {
            $form['expiry_days'] = $choice;
        }

        // check the fields
        $field_errors = array();
        if ($form['pharmacy_name'] == "") {
            $field_errors['pharmacy_name'] = "Pharmacy name is required.";
        } elseif (strlen($form['pharmacy_name']) > 100) {
            $field_errors['pharmacy_name'] = "Use 100 characters or less.";
        }
        if (strlen($form['address']) > 200) {
            $field_errors['address'] = "Use 200 characters or less.";
        }
        if ($form['contact_number'] != "" && !preg_match('/^[0-9 +()-]{7,30}$/', $form['contact_number'])) {
            $field_errors['contact_number'] = "Use numbers only (7 to 30 characters).";
        }
        if ($form['email'] != "" && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = "Enter a valid email address.";
        }
        if (strlen($form['receipt_header']) > 100) {
            $field_errors['receipt_header'] = "Use 100 characters or less.";
        }
        if (strlen($form['receipt_footer']) > 150) {
            $field_errors['receipt_footer'] = "Use 150 characters or less.";
        }
        if (!ctype_digit($form['default_min'])) {
            $field_errors['default_min'] = "Enter a whole number (0 or more).";
        }
        if (!ctype_digit($form['default_max'])) {
            $field_errors['default_max'] = "Enter a whole number.";
        } elseif (ctype_digit($form['default_min']) && (int) $form['default_max'] <= (int) $form['default_min']) {
            $field_errors['default_max'] = "Maximum must be higher than the minimum.";
        }
        if (!ctype_digit((string) $form['expiry_days']) || $form['expiry_days'] < 1 || $form['expiry_days'] > 365) {
            $field_errors['expiry_days'] = "Enter a number of days from 1 to 365.";
        }

        // no errors: save with the stored procedure
        if (count($field_errors) == 0) {
            Proc::call('sp_save_settings', array(
                $form['pharmacy_name'], $form['address'], $form['contact_number'], $form['email'],
                $form['receipt_header'], $form['receipt_footer'], $form['print_receipt'],
                (int) $form['default_min'], (int) $form['default_max'], (int) $form['expiry_days'],
                session('user_id')
            ));

            return redirect()->route('settings')->with('flash', array("type" => "success", "text" => "Settings saved."));
        }

        return $this->showForm($form, $field_errors);
    }

    // Reset to Defaults button
    public function reset(Request $request)
    {
        Proc::call('sp_reset_settings', array(session('user_id')));

        return redirect()->route('settings')->with('flash', array("type" => "success", "text" => "Settings were reset to the default values."));
    }

    // builds the page (used by index and save)
    private function showForm($form, $field_errors)
    {
        // near-expiry buttons: 30, 60, 90 days or Custom
        $expiry_choice = "custom";
        if (in_array($form['expiry_days'], array(30, 60, 90))) {
            $expiry_choice = (string) $form['expiry_days'];
        }

        // header settings
        $page_title = "Settings";
        $page_subtitle = "Configure your pharmacy profile, receipts, and inventory defaults";
        $current_page = "settings";

        return view('settings', compact('form', 'field_errors', 'expiry_choice', 'page_title', 'page_subtitle', 'current_page'));
    }
}
