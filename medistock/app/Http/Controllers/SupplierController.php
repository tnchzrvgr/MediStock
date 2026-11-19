<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Suppliers page
class SupplierController extends Controller
{
    public function index(Request $request)
    {
        // numbers for the 4 cards
        $kpi_rows = Proc::call('sp_supplier_kpis');
        if (count($kpi_rows) == 0) {
            abort(500, "No supplier data found. Please run suppliers.sql in phpMyAdmin first.");
        }
        $kpi = $kpi_rows[0];

        // the supplier directory
        $suppliers = Proc::call('sp_supplier_list');

        // header settings
        $page_title = "Suppliers";
        $page_subtitle = "Manage your suppliers and their deliveries";
        $current_page = "suppliers";


        return view('suppliers', compact('kpi', 'suppliers', 'page_title', 'page_subtitle', 'current_page'));
    }

    // ---------- Add / Edit supplier ----------

    // the choices of the form
    private function getProductList()
    {
        return array("Antibiotics", "Analgesics", "Cardiovascular", "Antidiabetic", "Respiratory", "Vitamins");
    }

    private function getTerms()
    {
        return array("Cash on delivery", "Net 7 days", "Net 15 days", "Net 30 days", "Net 60 days");
    }

    // show the form (empty for a new supplier, or filled when the link has ?id=)
    public function form(Request $request)
    {
        $supplier_id = (int) $request->query('id', 0);

        // starting values of the form
        $form = array(
            "supplier_name"  => "",
            "contact_person" => "",
            "phone"          => "",
            "email"          => "",
            "address"        => "",
            "products"       => array(),
            "payment_terms"  => "Net 30 days",
            "lead_time_days" => "",
            "status"         => "Active",
            "notes"          => ""
        );

        // editing: load the supplier from the database
        if ($supplier_id > 0) {
            $found = Proc::call('sp_get_supplier', array($supplier_id));
            if (count($found) == 0) {
                return redirect()->route('suppliers')->with('flash', array("type" => "error", "text" => "Supplier not found."));
            }
            $row = $found[0];

            $form['supplier_name'] = $row['supplier_name'];
            $form['contact_person'] = $row['contact_person'];
            $form['phone'] = $row['phone'];
            $form['email'] = $row['email'];
            $form['address'] = $row['address'];
            $form['products'] = array_map('trim', explode(",", $row['products_supplied']));
            $form['payment_terms'] = $row['payment_terms'];
            $form['lead_time_days'] = $row['lead_time_days'];
            $form['notes'] = $row['notes'];

            $form['status'] = $row['status'];
        }

        return $this->showForm($supplier_id, $form, array());
    }

    // Save Supplier button
    public function save(Request $request)
    {
        $supplier_id = (int) $request->input('supplier_id', 0);

        $form = array();
        $form['supplier_name']  = trim($request->input('supplier_name', ''));
        $form['contact_person'] = trim($request->input('contact_person', ''));
        $form['phone']          = trim($request->input('phone', ''));
        $form['email']          = trim($request->input('email', ''));
        $form['address']        = trim($request->input('address', ''));
        $form['products']       = (array) $request->input('products', array());
        $form['payment_terms']  = $request->input('payment_terms', '');
        $form['lead_time_days'] = trim($request->input('lead_time_days', ''));
        $form['status']         = trim($request->input('status', 'Active'));
        $form['notes']          = trim($request->input('notes', ''));

        // check the fields
        $field_errors = array();
        if ($form['supplier_name'] == "") {
            $field_errors['supplier_name'] = "Supplier name is required.";
        } elseif (strlen($form['supplier_name']) > 100) {
            $field_errors['supplier_name'] = "Use 100 characters or less.";
        }
        if ($form['contact_person'] == "") {
            $field_errors['contact_person'] = "Contact person is required.";
        }
        if ($form['phone'] == "") {
            $field_errors['phone'] = "Phone is required.";
        } elseif (!preg_match('/^[0-9 +()-]{7,20}$/', $form['phone'])) {
            $field_errors['phone'] = "Use numbers only (7 to 20 characters).";
        }
        if ($form['email'] != "" && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = "Enter a valid email address.";
        }
        if ($form['address'] == "") {
            $field_errors['address'] = "Address is required.";
        }

        // only letters, spaces and dashes are allowed in the product names
        $products = array();
        foreach ($form['products'] as $product) {
            if (preg_match('/^[A-Za-z -]{1,30}$/', $product)) {
                $products[] = $product;
            }
        }
        $form['products'] = $products;
        if (count($products) == 0) {
            $field_errors['products'] = "Select at least one category.";
        }

        if (!in_array($form['status'], array("Active", "On Hold", "Inactive"))) {
            $field_errors['status'] = "Please choose the status.";
        }
        if (!in_array($form['payment_terms'], $this->getTerms())) {
            $field_errors['payment_terms'] = "Please choose the payment terms.";
        }
        if ($form['lead_time_days'] != "" && (!ctype_digit($form['lead_time_days']) || (int) $form['lead_time_days'] > 90)) {
            $field_errors['lead_time_days'] = "Enter a number of days from 0 to 90.";
        }
        if (strlen($form['notes']) > 200) {
            $field_errors['notes'] = "Use 200 characters or less.";
        }

        $duplicate = false;

        // no errors: save with the stored procedure
        if (count($field_errors) == 0) {
            $status = $form['status'];

            $values = array(
                $form['supplier_name'], $form['contact_person'], $form['phone'], $form['email'], $form['address'],
                implode(", ", $products), $form['payment_terms'], (int) $form['lead_time_days'],
                $status, $form['notes'], session('user_id')
            );

            if ($supplier_id > 0) {
                array_unshift($values, $supplier_id);
                $answer = Proc::call('sp_update_supplier', $values);
            } else {
                $answer = Proc::call('sp_add_supplier', $values);
            }

            if (count($answer) > 0 && $answer[0]['result'] == "ok") {
                if ($supplier_id > 0) {
                    $text = "Supplier updated.";
                } else {
                    $text = "Supplier added.";
                }
                return redirect()->route('suppliers')->with('flash', array("type" => "success", "text" => $text));
            }

            $duplicate = true;
            $field_errors['supplier_name'] = "A supplier with this name and phone already exists (duplicate check).";
        }

        return $this->showForm($supplier_id, $form, $field_errors);
    }

    // builds the page (used by form and save)
    private function showForm($supplier_id, $form, $field_errors)
    {
        // the 6 category buttons, plus any other product the supplier already has
        $product_list = $this->getProductList();
        foreach ($form['products'] as $product) {
            if (!in_array($product, $product_list)) {
                $product_list[] = $product;
            }
        }
        $terms = $this->getTerms();

        // header settings
        if ($supplier_id > 0) {
            $page_title = "Edit Supplier";
            $page_subtitle = "Suppliers › Edit supplier";
        } else {
            $page_title = "Add Supplier";
            $page_subtitle = "Suppliers › New supplier";
        }
        $current_page = "suppliers";

        return view('add_supplier', compact('supplier_id', 'form', 'field_errors', 'product_list', 'terms',
            'page_title', 'page_subtitle', 'current_page'));
    }

    // ---------- Supplier details page (the View link in Suppliers) ----------

    public function show(Request $request, $id)
    {
        $found = Proc::call('sp_supplier_detail', array((int) $id));
        if (count($found) == 0) {
            return redirect()->route('suppliers')->with('flash', array("type" => "error", "text" => "Supplier not found."));
        }
        $supplier = $found[0];

        // the products are saved as one text (example: "Antibiotics, Analgesics")
        $products = array_map('trim', explode(",", $supplier['products_supplied']));

        // the last deliveries recorded in Stock-In
        $deliveries = Proc::call('sp_supplier_deliveries', array($supplier['supplier_name']));

        // the newest delivery is the first row
        $last_delivery = "—";
        if (count($deliveries) > 0) {
            $last_delivery = date("M d, Y", strtotime($deliveries[0]['date_received']));
        }

        // header settings
        $page_title = $supplier['supplier_name'];
        $page_subtitle = "Suppliers › Supplier details";
        $current_page = "suppliers";

        return view('supplier_view', compact('supplier', 'products', 'last_delivery', 'deliveries',
            'page_title', 'page_subtitle', 'current_page'));
    }
}
