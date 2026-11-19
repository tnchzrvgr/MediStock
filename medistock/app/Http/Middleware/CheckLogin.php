<?php

namespace App\Http\Middleware;

use App\Support\Proc;
use Closure;
use Illuminate\Http\Request;

// every page except the login page needs a logged in user
// and the user is logged out after some minutes without using the system (Backup & Security page)
class CheckLogin
{
    public function handle(Request $request, Closure $next)
    {
        if (!session()->has('user_id')) {
            return redirect()->route('login');
        }

        // minutes of inactivity allowed (15 if the setting cannot be read)
        $timeout = 15;
        try {
            $settings = Proc::call('sp_backup_settings');
            if (count($settings) > 0) {
                $timeout = (int) $settings[0]['session_timeout'];
            }
        } catch (\Exception $e) {
            // backup.sql was not run yet, use 15 minutes
        }

        $last = session('last_activity');
        if ($last != null && time() - $last > $timeout * 60) {
            session()->flush();
            return redirect()->route('login')->with('timeout_message', "You were logged out after " . $timeout . " minutes of inactivity.");
        }

        session(['last_activity' => time()]);

        return $next($request);
    }
}
