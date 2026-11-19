<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Receipt page (the link can have a sale id, without an id it shows the last sale)
class ReceiptController extends Controller
{
    public function show(Request $request, $id = 0)
    {
        $header_rows = Proc::call('sp_receipt_header', array((int) $id));
        if (count($header_rows) == 0) {
            return redirect()->route('sales')->with('flash', array("type" => "error", "text" => "No receipt found yet. Make a sale first."));
        }
        $sale = $header_rows[0];

        // old databases (receipt.sql not imported again) have no status
        if (!isset($sale['status'])) {
            $sale['status'] = "Completed";
        }
        $items = Proc::call('sp_receipt_items', array($sale['sale_id']));

        // the pharmacy name, address and footer come from the Settings page
        $shop = array(
            "pharmacy_name" => "MediStock Pharmacy",
            "address" => "",
            "contact_number" => "",
            "receipt_footer" => "Thank you! Please keep this receipt.",
            "print_receipt" => 0
        );
        try {
            $settings = Proc::call('sp_get_settings');
            if (count($settings) > 0) {
                $shop = $settings[0];
            }
        } catch (\Exception $e) {
            // settings.sql was not run yet, the default texts are used
        }

        // each line of the receipt and the subtotal
        $subtotal = 0;
        foreach ($items as $key => $item) {
            $items[$key]['line_total'] = $item['quantity'] * $item['unit_price'];
            $subtotal = $subtotal + $items[$key]['line_total'];
        }

        // the total is saved with the sale. Old sales without items use it too.
        $total = $sale['total_amount'];

        // only cash has an amount received and a change
        $received = $sale['amount_received'];
        if ($received == null) {
            $received = $total;
        }
        $change = $received - $total;

        // true right after the sale was completed (the link has ?just=1)
        $just_completed = ($request->query('just') == 1);

        // header settings
        $page_title = "Receipt";
        $page_subtitle = "Sale completed — view, print or download the receipt";
        $current_page = "sales";

        return view('receipt', compact('sale', 'items', 'shop', 'subtotal', 'total', 'received', 'change',
            'just_completed', 'page_title', 'page_subtitle', 'current_page'));
    }
}
