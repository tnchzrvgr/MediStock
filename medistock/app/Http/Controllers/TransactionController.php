<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Transaction History page (search past sales, Admin can void or return a sale)
class TransactionController extends Controller
{
    public function index(Request $request)
    {
        // the date ranges of the drop-down list
        $ranges = array(
            "7"     => "Last 7 days",
            "30"    => "Last 30 days",
            "month" => "This month",
            "90"    => "Last 90 days",
            "year"  => "This year",
            "all"   => "All time"
        );
        $statuses = array("Completed", "Returned", "Voided");

        // what the user chose (only the allowed values are used)
        $item = trim($request->query('item', ''));

        $range = $request->query('range', '30');
        if (!isset($ranges[$range])) {
            $range = "30";
        }

        $staff = (int) $request->query('staff', 0);

        $status = $request->query('status', '');
        if (!in_array($status, $statuses)) {
            $status = "";
        }

        // dates of the chosen range
        $to = date("Y-m-d");
        if ($range == "month") {
            $from = date("Y-m-01");
        } elseif ($range == "year") {
            $from = date("Y-01-01");
        } elseif ($range == "all") {
            $from = "2000-01-01";
        } else {
            $from = date("Y-m-d", strtotime("-" . $range . " days"));
        }

        // the search (stored procedure) and the staff list for the drop-down
        $sales = Proc::call('sp_transactions', array($item, $from, $to, $staff, $status));
        $staff_list = Proc::call('sp_report_staff');

        // the Void / Return window (opened by the Void and Return links, only for the Admin)
        $modal = null;
        $modal_items = array();
        $mode = "void";
        if ($request->query('mode') == "return") {
            $mode = "return";
        }
        $modal_id = (int) $request->query('modal', 0);

        if ($modal_id > 0 && session('role') == "Admin") {
            $found = Proc::call('sp_receipt_header', array($modal_id));
            if (count($found) > 0 && $found[0]['status'] == "Completed") {
                $modal = $found[0];
                $modal_items = Proc::call('sp_receipt_items', array($modal_id));
            }
        }

        // the reasons of the drop-down list
        $reasons = $this->getReasons();

        // header settings
        $page_title = "Transaction History";
        $page_subtitle = "Search past sales by date, item or staff — void or return (admin only)";
        $current_page = "sales";

        return view('transactions', compact('sales', 'staff_list', 'ranges', 'statuses', 'item', 'range', 'staff', 'status', 'modal', 'modal_items', 'mode', 'reasons',
            'page_title', 'page_subtitle', 'current_page'));
    }

    // the reasons that can be picked
    private function getReasons()
    {
        return array("Wrong item entered", "Wrong quantity entered", "Customer changed mind",
                     "Damaged or defective item", "Expired item", "Duplicate sale");
    }

    // Confirm button of the Void / Return window (only the Owner / Admin, see the "admin" middleware)
    public function change(Request $request)
    {
        $sale_id = (int) $request->input('sale_id');
        $mode = "void";
        if ($request->input('mode') == "return") {
            $mode = "return";
        }
        $reason = $request->input('reason', '');
        $password = $request->input('admin_password', '');

        // go back to the window with a message
        $back = array('modal' => $sale_id, 'mode' => $mode);

        if (!in_array($reason, $this->getReasons())) {
            return redirect()->route('transactions', $back)->with('flash', array("type" => "error", "text" => "Please choose a reason."));
        }

        // the admin must type his own password again
        $rows = Proc::call('sp_login_user', array(session('username')));
        if (count($rows) == 0 || $password == "" || !password_verify($password, $rows[0]['password'])) {
            return redirect()->route('transactions', $back)->with('flash', array("type" => "error", "text" => "The admin password is not correct."));
        }

        // save everything, or nothing (transaction)
        DB::beginTransaction();

        if ($mode == "void") {
            $answer = Proc::call('sp_void_sale', array($sale_id, "Voided", $reason, session('user_id')));
            $result = count($answer) > 0 ? $answer[0]['result'] : "";
            $done_text = "Sale voided. The stock was put back and the action is in the activity log.";
        } else {
            // the quantities typed for each medicine (medicine_id => quantity)
            $quantities = (array) $request->input('return_qty', array());

            $answer = Proc::call('sp_return_start', array($sale_id, $reason, session('user_id')));
            $result = count($answer) > 0 ? $answer[0]['result'] : "";

            $returned = 0;
            if ($result == "ok") {
                foreach ($quantities as $medicine_id => $qty) {
                    $qty = trim($qty);
                    if ($qty == "" || $qty == "0") {
                        continue;
                    }

                    $item = Proc::call('sp_return_item', array($sale_id, (int) $medicine_id, ctype_digit($qty) ? (int) $qty : 0));
                    if (count($item) == 0 || $item[0]['result'] != "ok") {
                        $result = "invalid";
                        break;
                    }
                    $returned++;
                }

                if ($result == "ok" && $returned == 0) {
                    $result = "nothing";
                }
            }

            if ($result == "ok") {
                Proc::call('sp_return_finish', array($sale_id, session('user_id')));
            }
            $done_text = "Items returned. The stock was put back and the action is in the activity log.";
        }

        if ($result == "ok") {
            DB::commit();
            return redirect()->route('transactions')->with('flash', array("type" => "success", "text" => $done_text));
        }

        DB::rollBack();
        if ($result == "already") {
            $text = "This sale was already voided or returned.";
        } elseif ($result == "invalid") {
            $text = "A return quantity is more than the quantity sold.";
        } elseif ($result == "nothing") {
            $text = "Enter the quantity of at least one item to return.";
        } else {
            $text = "Sale not found.";
        }
        return redirect()->route('transactions', $back)->with('flash', array("type" => "error", "text" => $text));
    }
}
