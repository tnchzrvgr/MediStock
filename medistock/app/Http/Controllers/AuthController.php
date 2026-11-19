<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// login and logout (our own simple login, not the Laravel auth package)
class AuthController extends Controller
{
    // show the login page
    public function showLogin(Request $request)
    {
        // if already logged in, go to dashboard
        if (session()->has('user_id')) {
            return redirect()->route('dashboard');
        }

        // fill the username if "remember me" was checked before
        $saved_username = $request->cookie('remember_user', '');

        // message when the user was logged out for inactivity
        $error = session('timeout_message', "");

        return view('login', compact('error', 'saved_username'));
    }

    // check the username and password
    public function login(Request $request)
    {
        $username = trim($request->input('username', ''));
        $password = $request->input('password', '');
        $error = "";
        $saved_username = "";

        if ($username == "" || $password == "") {
            $error = "Please fill in all fields.";
            return view('login', compact('error', 'saved_username'));
        }

        // call the stored procedure (no direct query to the users table)
        $rows = Proc::call('sp_login_user', array($username));
        $row = null;
        if (count($rows) > 0) {
            $row = $rows[0];
        }

        $password_ok = $row && password_verify($password, $row['password']);

        if ($password_ok && $row['is_active'] == 0) {
            // right password, but the account was deactivated by the admin
            $error = "This account is deactivated. Please contact the administrator.";
            $saved_username = $username;
            return view('login', compact('error', 'saved_username'));
        }

        if (!$password_ok) {
            $error = "Invalid username or password.";
            $saved_username = $username;
            return view('login', compact('error', 'saved_username'));
        }

        // step 2 of the login: a user with a registered face must also pass the face check
        if (face_login_on()) {
            $has_face = false;
            try {
                $has_face = count(Proc::call('sp_face_get', array((int) $row['user_id']))) > 0;
            } catch (\Exception $e) {
                // face_login.sql was not run yet, so the password is enough
            }

            if ($has_face) {
                session(['face_pending' => array(
                    "user_id"   => $row['user_id'],
                    "username"  => $row['username'],
                    "full_name" => $row['full_name'],
                    "role"      => $row['role'],
                    "remember"  => $request->has('remember')
                )]);
                session(['face_tries' => 0]);
                return redirect()->route('login.face');
            }
        }

        return $this->finishLogin($request, $row, $request->has('remember'));
    }

    // the user is accepted: save the login time and start the session
    // (called after the password, or after the password AND the face check)
    public function finishLogin(Request $request, $row, $remember)
    {
        // save the time of this login
        Proc::call('sp_record_login', array((int) $row['user_id']));

        // new session id for safety
        $request->session()->regenerate();

        session(['user_id' => $row['user_id']]);
        session(['username' => $row['username']]);
        session(['full_name' => $row['full_name']]);
        session(['role' => $row['role']]);

        // remember me (saves the username for 7 days)
        if ($remember) {
            $cookie = cookie('remember_user', $row['username'], 60 * 24 * 7);
        } else {
            $cookie = cookie()->forget('remember_user');
        }

        return redirect()->route('dashboard')->withCookie($cookie);
    }

    // logout
    public function logout(Request $request)
    {
        $request->session()->flush();
        $request->session()->regenerate();

        return redirect()->route('login');
    }
}
