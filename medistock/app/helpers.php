<?php
// small helper functions used by the controllers and the views
// (this file is loaded in app/Providers/AppServiceProvider.php)

// gives back the red error text under a field (empty if there is no error)
function show_error($field_errors, $field) {
    if (isset($field_errors[$field])) {
        return '<span class="field-error">' . e($field_errors[$field]) . '</span>';
    }
    return "";
}

// adds the red border to a field that has an error
function error_class($field_errors, $field) {
    if (isset($field_errors[$field])) {
        return " input-error";
    }
    return "";
}

// "unit" or "units" (the unit is saved in plural, example: vials)
function unit_text($unit, $count) {
    if ($count == 1 && substr($unit, -1) == "s") {
        return substr($unit, 0, -1);
    }
    return $unit;
}

// draws a straight line through the numbers and gives back the predicted value
// at the given position (used for the demand forecast)
function predict_next($values, $position) {
    $n = count($values);
    $sum_x = 0;
    $sum_y = 0;
    $sum_xy = 0;
    $sum_xx = 0;

    for ($i = 0; $i < $n; $i++) {
        $sum_x = $sum_x + $i;
        $sum_y = $sum_y + $values[$i];
        $sum_xy = $sum_xy + ($i * $values[$i]);
        $sum_xx = $sum_xx + ($i * $i);
    }

    $bottom = ($n * $sum_xx) - ($sum_x * $sum_x);
    if ($bottom == 0) {
        return $values[0];
    }

    $slope = (($n * $sum_xy) - ($sum_x * $sum_y)) / $bottom;
    $intercept = ($sum_y - ($slope * $sum_x)) / $n;

    $result = $intercept + ($slope * $position);
    if ($result < 0) {
        $result = 0;
    }
    return round($result);
}

// "1 medicine" or "5 medicines"
function medicine_count_text($count) {
    if ($count == 1) {
        return "1 medicine";
    }
    return $count . " medicines";
}

// "1 day" or "5 days"
function day_text($days) {
    if ($days == 1) {
        return "1 day";
    }
    return $days . " days";
}

// color of the stock badge in the search results
function badge_class($status) {
    if ($status == "In Stock") {
        return "pill-up";
    }
    if ($status == "Overstock") {
        return "pill-amber";
    }
    return "pill-danger";
}

function badge_text($status) {
    if ($status == "In Stock") {
        return "Normal";
    }
    if ($status == "Low Stock" || $status == "Out of Stock") {
        return "Low";
    }
    return $status;
}

// writes a money value like ₱ 1,280.00
function money($amount) {
    return "₱ " . number_format($amount, 2);
}

// writes "1 item" or "5 items"
function items_text($count) {
    if ($count == 1) {
        return "1 item";
    }
    return $count . " items";
}

// works out the % change between two numbers
// gives back the pill color and the text (example: "▲ 12.4% vs yesterday")
function change_info($now, $before, $label) {
    if ($before > 0) {
        $change = (($now - $before) / $before) * 100;
    } else {
        $change = 0;
    }

    if ($change >= 0) {
        $class = "pill-up";
        $arrow = "▲";
    } else {
        $class = "pill-down";
        $arrow = "▼";
    }

    $text = $arrow . " " . number_format(abs($change), 1) . "% " . $label;
    return array($class, $text);
}

// says in plain words how much more or less than before (example: "▲ ₱ 500 more than last week")
// $before_text is "yesterday", "last week" or "last month"; $is_money is false for plain counts
function change_words($now, $before, $before_text, $is_money = true) {
    $diff = $now - $before;

    if ($now == 0 && $before == 0) {
        return array("pill-blue", "No sales yet");
    }
    if ($diff == 0) {
        return array("pill-blue", "Same as " . $before_text);
    }

    $amount = $is_money ? "₱" . number_format(abs($diff)) : number_format(abs($diff));
    if ($diff > 0) {
        return array("pill-up", "▲ " . $amount . " more than " . $before_text);
    }
    return array("pill-down", "▼ " . $amount . " less than " . $before_text);
}

// checks that a text like 2026-09-22 is a real date
function is_date($text) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
        return false;
    }
    $p = explode("-", $text);
    return checkdate((int)$p[1], (int)$p[2], (int)$p[0]);
}

// color of the status text
function status_color($status) {
    if ($status == "Active" || $status == "Approved") {
        return "status-green";
    }
    if ($status == "Delivery Delayed" || $status == "Delayed") {
        return "status-amber";
    }
    if ($status == "On Hold") {
        return "status-red";
    }
    if ($status == "In Transit") {
        return "status-blue";
    }
    return "status-gray";
}

// the role is saved as "Admin" or "Staff" but the pages show "Owner / Admin" and "Pharmacy Staff"
function role_label($role) {
    if ($role == "Admin") {
        return "Owner / Admin";
    }
    if ($role == "Staff") {
        return "Pharmacy Staff";
    }
    return $role;
}

// true when the logged in user is the Owner / Admin
function is_admin() {
    return session('role') == "Admin";
}

// color of the action text in the activity log
function action_color($action) {
    if ($action == "Sale Recorded" || $action == "User Reactivated") {
        return "status-green";
    }
    if ($action == "Stock Updated" || $action == "Backup Created") {
        return "status-blue";
    }
    if ($action == "User Added" || $action == "User Edited" || $action == "Supplier Added" || $action == "Supplier Edited" || $action == "Backup Restored" || $action == "Sale Returned" || $action == "Settings Changed") {
        return "status-amber";
    }
    if ($action == "Face Registered" || $action == "Face Login") {
        return "status-green";
    }
    if ($action == "User Deactivated" || $action == "Sale Voided" || $action == "Face Removed" || $action == "Face Login Failed") {
        return "status-red";
    }
    return "status-gray";
}


// size of a backup file (example: 248 MB, 36 KB)
function file_size_text($bytes) {
    if ($bytes <= 0) {
        return "—";
    }
    if ($bytes >= 1024 * 1024) {
        return str_replace(".0 ", " ", number_format($bytes / (1024 * 1024), 1) . " ") . "MB";
    }
    return number_format(max(1, $bytes / 1024), 0) . " KB";
}

// color of the sale status in the Transaction History table
function sale_status_color($status) {
    if ($status == "Completed") {
        return "status-green";
    }
    if ($status == "Returned") {
        return "status-amber";
    }
    return "status-red";
}

// ---------------------------------------------------------
// face login helpers
// ---------------------------------------------------------

// face login can be switched off with FACE_LOGIN=false in the .env file
function face_login_on() {
    $value = env('FACE_LOGIN', true);
    return !($value === false || $value === "false" || $value === "0" || $value === 0);
}

// how different two faces may be (lower = stricter).
// 0.50 is stricter than the usual 0.60. Lower = stricter (fewer wrong people accepted, more right people refused).
// login: live face vs saved face must be under 0.50 (relatives can look alike)
// ID photo vs live face: under 0.55 (an ID photo is small, older and the hair/looks may be different)
function face_login_limit() {
    return 0.50;
}

// the ID scanned at login must be the SAME ID that was registered (same card photo): stricter than a face match
function face_same_id_limit() {
    return 0.50;
}

function face_id_limit() {
    return 0.55;
}

// turns the 128 numbers (array or JSON text) into a clean array of numbers, or null when it is not valid
function face_numbers($value) {
    if (is_string($value)) {
        $value = json_decode($value, true);
    }
    if (!is_array($value) || count($value) != 128) {
        return null;
    }
    $numbers = array();
    foreach ($value as $one) {
        if (!is_numeric($one)) {
            return null;
        }
        $numbers[] = (float) $one;
    }
    return $numbers;
}

// distance between two faces (Euclidean distance of the 128 numbers)
function face_distance($a, $b) {
    $sum = 0;
    for ($i = 0; $i < 128; $i++) {
        $diff = $a[$i] - $b[$i];
        $sum += $diff * $diff;
    }
    return sqrt($sum);
}
