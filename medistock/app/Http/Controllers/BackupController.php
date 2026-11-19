<?php

namespace App\Http\Controllers;

use App\Support\Backup;
use App\Support\Proc;
use Illuminate\Http\Request;

// Backup & Security page (only the Owner / Admin, see the "admin" middleware)
class BackupController extends Controller
{
    public function index(Request $request)
    {
        $settings_rows = Proc::call('sp_backup_settings');
        if (count($settings_rows) == 0) {
            abort(500, "No backup settings found. Please run backup.sql in phpMyAdmin first.");
        }
        $settings = $settings_rows[0];

        $history = Proc::call('sp_backup_history');

        // the choices of the drop-down lists and buttons
        $schedules = array("Manual", "Daily", "Weekly");
        $times = array();
        for ($hour = 0; $hour < 24; $hour++) {
            $value = sprintf("%02d:00:00", $hour);
            $times[$value] = date("g:i A", strtotime($value));
        }
        $keep_options = array(7, 14, 30, 60);
        $timeouts = array(5 => "5 min", 15 => "15 min", 30 => "30 min", 60 => "1 hour");

        // card 1: last backup
        $last_text = "No backups yet";
        $last_status = "Not done";
        $last_class = "pill-amber";
        $size_text = "—";
        if (count($history) > 0) {
            $last = $history[0];
            $last_text = $this->whenText($last['created_at']);
            if ($last['status'] == "Successful") {
                $last_status = "✓ Successful";
                $last_class = "pill-up";
            } else {
                $last_status = "Failed";
                $last_class = "pill-down";
            }

            // card 2: size of the newest successful backup
            foreach ($history as $row) {
                if ($row['status'] == "Successful") {
                    $size_text = file_size_text($row['size_bytes']);
                    break;
                }
            }
        }

        // card 3: next scheduled backup
        $next_text = "Not scheduled";
        $next_note = "Manual only";
        if ($settings['schedule'] != "Manual") {
            $next_time = strtotime(date("Y-m-d") . " " . $settings['backup_time']);

            if ($settings['schedule'] == "Daily") {
                if ($next_time <= time()) {
                    $next_time = strtotime("+1 day", $next_time);
                }
            } else {
                // weekly: every Monday
                while (date("N", $next_time) != 1 || $next_time <= time()) {
                    $next_time = strtotime("+1 day", $next_time);
                }
            }
            $next_text = $this->whenText(date("Y-m-d H:i:s", $next_time));
            $next_note = $settings['schedule'] . " schedule";
        }

        // the rows of the history table
        $history_rows = array();
        foreach ($history as $row) {
            $history_rows[] = array(
                "id" => $row['backup_id'],
                "date" => date("M j, Y g:i A", strtotime($row['created_at'])),
                "type" => $row['backup_type'],
                "size" => file_size_text($row['size_bytes']),
                "status" => $row['status']
            );
        }

        // header settings
        $page_title = "Backup & Security";
        $page_subtitle = "Protect your data with backups and session control";
        $current_page = "backup";

        return view('backup', compact('settings', 'schedules', 'times', 'keep_options', 'timeouts',
            'last_text', 'last_status', 'last_class', 'size_text', 'next_text', 'next_note', 'history_rows',
            'page_title', 'page_subtitle', 'current_page'));
    }

    // "Today, 2:00 AM", "Tomorrow, 2:00 AM" or "Nov 10, 2:00 AM"
    private function whenText($datetime)
    {
        $time = strtotime($datetime);
        $day = date("Y-m-d", $time);

        if ($day == date("Y-m-d")) {
            $text = "Today";
        } elseif ($day == date("Y-m-d", strtotime("+1 day"))) {
            $text = "Tomorrow";
        } else {
            $text = date("M j", $time);
        }
        return $text . ", " . date("g:i A", $time);
    }

    // go back to the page with a message
    private function goBack($type, $text)
    {
        return redirect()->route('backup')->with('flash', array("type" => $type, "text" => $text));
    }

    // Back Up Now button
    public function backupNow(Request $request)
    {
        list($ok, $message) = Backup::create("Manual", session('user_id'));

        if ($ok) {
            return $this->goBack("success", $message);
        }
        return $this->goBack("error", $message);
    }

    // Retry link of a failed backup
    public function retry(Request $request)
    {
        return $this->backupNow($request);
    }

    // Save Settings buttons (the hidden field "section" tells which card was saved)
    public function save(Request $request)
    {
        $by = session('user_id');

        if ($request->input('section') == "backup") {
            $schedule = $request->input('schedule');
            $time = $request->input('backup_time');
            $keep = (int) $request->input('keep_last');

            if (!in_array($schedule, array("Manual", "Daily", "Weekly")) || !preg_match('/^\d\d:00:00$/', $time) || !in_array($keep, array(7, 14, 30, 60))) {
                return $this->goBack("error", "Please choose valid backup settings.");
            }

            Proc::call('sp_save_backup_settings', array($schedule, $time, $keep, $by));
            return $this->goBack("success", "Backup settings saved.");
        }

        // security card
        $timeout = (int) $request->input('session_timeout');
        if (!in_array($timeout, array(5, 15, 30, 60))) {
            return $this->goBack("error", "Please choose a session timeout.");
        }

        Proc::call('sp_save_security_settings', array($timeout, $by));
        return $this->goBack("success", "Security settings saved.");
    }

    // Restore from File button (the user picks a .sql backup file)
    public function restoreFile(Request $request)
    {
        $file = $request->file('backup_file');
        if ($file == null || !$file->isValid()) {
            return $this->goBack("error", "Please choose a backup file.");
        }

        list($ok, $message) = Backup::restore(file_get_contents($file->getRealPath()));
        if ($ok) {
            Proc::call('sp_log_restore', array(session('user_id'), $file->getClientOriginalName()));
            return $this->goBack("success", "Database restored from " . $file->getClientOriginalName() . ".");
        }
        return $this->goBack("error", $message);
    }

    // Restore link in the history table
    public function restoreHistory(Request $request)
    {
        $found = Proc::call('sp_get_backup', array((int) $request->input('backup_id')));
        if (count($found) == 0 || $found[0]['status'] != "Successful") {
            return $this->goBack("error", "Backup not found.");
        }

        // basename() so only a file inside the backups folder can be used
        $path = Backup::folder() . "/" . basename($found[0]['file_name']);
        if (!file_exists($path)) {
            return $this->goBack("error", "The backup file is missing on the server.");
        }

        list($ok, $message) = Backup::restore(file_get_contents($path));
        if ($ok) {
            Proc::call('sp_log_restore', array(session('user_id'), $found[0]['file_name']));
            return $this->goBack("success", "Database restored from " . $found[0]['file_name'] . ".");
        }
        return $this->goBack("error", $message);
    }

    // Download link in the history table
    public function download(Request $request, $id)
    {
        $found = Proc::call('sp_get_backup', array((int) $id));
        if (count($found) == 0 || $found[0]['status'] != "Successful") {
            return $this->goBack("error", "Backup not found.");
        }

        $path = Backup::folder() . "/" . basename($found[0]['file_name']);
        if (!file_exists($path)) {
            return $this->goBack("error", "The backup file is missing on the server.");
        }

        return response()->download($path);
    }
}
