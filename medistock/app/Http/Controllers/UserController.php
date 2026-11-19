<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// User Management pages (only the Owner / Admin can open them, see the "admin" middleware)
class UserController extends Controller
{
    // list of users and the activity log
    public function index(Request $request)
    {
        // numbers for the 4 cards
        $kpi_rows = Proc::call('sp_user_kpis');
        if (count($kpi_rows) == 0) {
            abort(500, "No user data found. Please run login.sql and then users.sql in phpMyAdmin first.");
        }
        $kpi = $kpi_rows[0];

        // the two tables
        $users = Proc::call('sp_user_list');
        $log = Proc::call('sp_activity_log');

        // which users have a face registered (nothing if face_login.sql was not run yet)
        $faces = array();
        try {
            foreach (Proc::call('sp_face_users') as $f) {
                $faces[$f['user_id']] = true;
            }
        } catch (\Exception $e) {
        }

        // header settings
        $page_title = "User Management";
        $page_subtitle = "Manage accounts, roles and review the activity log";
        $current_page = "users";

        return view('users', compact('kpi', 'users', 'log', 'faces', 'page_title', 'page_subtitle', 'current_page'));
    }

    // Deactivate / Reactivate button
    public function setActive(Request $request)
    {
        $target = (int) $request->input('user_id');
        $active = 0;
        if ($request->input('action') == "reactivate") {
            $active = 1;
        }

        if ($target == session('user_id')) {
            // an admin cannot lock himself out
            $flash = array("type" => "error", "text" => "You cannot deactivate your own account.");
        } else {
            Proc::call('sp_set_user_active', array($target, $active, (int) session('user_id')));
            if ($active == 1) {
                $flash = array("type" => "success", "text" => "Account reactivated.");
            } else {
                $flash = array("type" => "success", "text" => "Account deactivated. The user can no longer log in.");
            }
        }

        return redirect()->route('users')->with('flash', $flash);
    }

    // show the add / edit form (the link has ?id= when editing)
    public function form(Request $request)
    {
        $user_id = (int) $request->query('id', 0);

        // starting values of the form
        $form = array("full_name" => "", "username" => "", "email" => "", "role" => "Staff");

        // editing: load the user from the database
        if ($user_id > 0) {
            $found = Proc::call('sp_get_user', array($user_id));
            if (count($found) == 0) {
                return redirect()->route('users')->with('flash', array("type" => "error", "text" => "User not found."));
            }
            $form = $found[0];
        }

        return $this->showForm($user_id, $form, array());
    }

    // save button
    public function save(Request $request)
    {
        $user_id = (int) $request->input('user_id', 0);
        $roles = $this->getRoles();

        $form = array();
        $form['full_name'] = trim($request->input('full_name', ''));
        $form['username']  = trim($request->input('username', ''));
        $form['email']     = trim($request->input('email', ''));
        $form['role']      = $request->input('role', '');
        $password          = $request->input('password', '');
        $password2         = $request->input('password2', '');

        // check the fields
        $field_errors = array();
        if ($form['full_name'] == "") {
            $field_errors['full_name'] = "Full name is required.";
        }
        if ($form['username'] == "") {
            $field_errors['username'] = "Username is required.";
        }
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $field_errors['email'] = "Enter a valid email address.";
        }
        if (!isset($roles[$form['role']])) {
            $field_errors['role'] = "Please choose a role.";
        }

        // the password is required for a new user, but optional when editing
        if ($user_id == 0 && $password == "") {
            $field_errors['password'] = "Password is required.";
        } elseif ($password != "" && strlen($password) < 6) {
            $field_errors['password'] = "Use at least 6 characters.";
        } elseif ($password != $password2) {
            $field_errors['password2'] = "The passwords do not match.";
        }

        // an admin cannot take away his own admin role
        if ($user_id == session('user_id') && $form['role'] != "Admin") {
            $field_errors['role'] = "You cannot change your own role.";
        }

        // no errors: save with the stored procedure
        if (count($field_errors) == 0) {
            $hash = "";
            if ($password != "") {
                $hash = password_hash($password, PASSWORD_DEFAULT);
            }
            $by = session('user_id');

            if ($user_id > 0) {
                $answer = Proc::call('sp_update_user', array(
                    $user_id, $form['full_name'], $form['username'], $form['email'], $hash, $form['role'], $by
                ));
            } else {
                $answer = Proc::call('sp_add_user', array(
                    $form['full_name'], $form['username'], $form['email'], $hash, $form['role'], $by
                ));
            }

            $result = "";
            if (count($answer) > 0) {
                $result = $answer[0]['result'];
            }

            if ($result == "ok") {
                if ($user_id > 0) {
                    $text = "User updated.";
                } else {
                    $text = "User added.";
                }
                return redirect()->route('users')->with('flash', array("type" => "success", "text" => $text));
            } elseif ($result == "username taken") {
                $field_errors['username'] = "This username is already used.";
            } elseif ($result == "email taken") {
                $field_errors['email'] = "This email is already used.";
            } else {
                $field_errors['full_name'] = "Could not save the user. Please try again.";
            }
        }

        return $this->showForm($user_id, $form, $field_errors);
    }

    // the roles that can be picked (the value is saved in the database)
    private function getRoles()
    {
        return array(
            "Admin" => "Owner / Admin",
            "Staff" => "Pharmacy Staff"
        );
    }

    private function showForm($user_id, $form, $field_errors)
    {
        $roles = $this->getRoles();

        // header settings
        $page_title = "Add / Edit User";
        $page_subtitle = "User Management › User account";
        $current_page = "users";

        return view('add_user', compact('user_id', 'form', 'field_errors', 'roles', 'page_title', 'page_subtitle', 'current_page'));
    }
}
