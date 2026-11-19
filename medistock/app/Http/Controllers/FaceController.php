<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Face login (step 2 after the password) and registering a user's face (Owner / Admin only).
// The camera program (face-api.js) runs in the browser and only sends 128 numbers.
// The comparison is done here on the server, so the browser cannot decide "match" by itself.
class FaceController extends Controller
{
    // ---------- login: face check ----------

    public function showLoginFace(Request $request)
    {
        $pending = session('face_pending');
        if ($pending == null) {
            return redirect()->route('login');
        }

        $name = $pending['full_name'];
        $models_url = asset('models');

        return view('login_face', compact('name', 'models_url'));
    }

    // the browser sends the numbers of the face that is in front of the camera
    public function checkLoginFace(Request $request)
    {
        $pending = session('face_pending');
        if ($pending == null) {
            return response()->json(array("ok" => false, "redirect" => route('login'), "message" => "Please sign in again."));
        }

        $live = face_numbers($request->input('descriptor'));
        $blinked = $request->input('blinked') == true;
        $on_id = face_numbers($request->input('id_descriptor'));
        $by_id = $on_id != null;       // the "scan my National ID" way (instead of the head turn)

        // the saved face of this user
        $rows = Proc::call('sp_face_get', array((int) $pending['user_id']));
        $saved = count($rows) > 0 ? face_numbers($rows[0]['descriptor']) : null;
        $saved_id = count($rows) > 0 && isset($rows[0]['id_descriptor']) ? face_numbers($rows[0]['id_descriptor']) : null;

        // an account registered before the ID check was added has no saved ID
        if ($by_id && $saved != null && $saved_id == null) {
            return response()->json(array("ok" => false, "redirect" => "",
                "message" => "No ID is registered for this account. Use the face check way, then ask the Owner to update your face (Register)."));
        }

        $ok = false;
        $score = 0;
        $id_message = "";
        if ($live != null && $saved != null) {
            $score = face_distance($live, $saved);
            $ok = $score < face_login_limit();

            if ($by_id) {
                // 4 checks: every refused check is told to the user with its score
                $checks = array(
                    array("your face does not match the registered face", $score, face_login_limit()),
                    array("the face on the ID does not match your face now", face_distance($live, $on_id), face_id_limit()),
                    array("the face on the ID does not match the registered face", face_distance($saved, $on_id), face_id_limit()),
                    array("this is not the same ID that was registered", face_distance($on_id, $saved_id), face_same_id_limit())
                );
                $problems = array();
                foreach ($checks as $check) {
                    if ($check[1] >= $check[2]) {
                        $problems[] = ucfirst($check[0]) . " (score " . number_format($check[1], 2) . ", must be below " . number_format($check[2], 2) . ")";
                    }
                }
                $ok = count($problems) == 0;
                $id_message = implode(". ", $problems);
                $score = max($score, face_distance($live, $on_id), face_distance($saved, $on_id), face_distance($on_id, $saved_id));
            } else {
                // the normal way: the person must have turned the head left and right
                $ok = $ok && $blinked;
            }
        }
        $score_text = number_format($score, 2);

        if ($ok) {
            Proc::call('sp_log_face', array((int) $pending['user_id'], 'Face Login', ($by_id ? "Face and National ID matched" : "Face matched") . " (score " . $score_text . ")"));

            $row = array("user_id" => $pending['user_id'], "username" => $pending['username'],
                         "full_name" => $pending['full_name'], "role" => $pending['role']);
            session()->forget('face_pending');
            session()->forget('face_tries');
            (new AuthController)->finishLogin($request, $row, $pending['remember']);

            $answer = response()->json(array("ok" => true, "redirect" => route('dashboard'), "message" => "Face matched."));

            // "remember me" was checked on the login page
            if ($pending['remember']) {
                $answer->withCookie(cookie('remember_user', $pending['username'], 60 * 24 * 7));
            }
            return $answer;
        }

        // wrong face: 3 tries only
        $tries = (int) session('face_tries', 0) + 1;
        session(['face_tries' => $tries]);
        Proc::call('sp_log_face', array((int) $pending['user_id'], 'Face Login Failed', "Face did not match (score " . $score_text . ", try " . $tries . ")"));

        if ($tries >= 3) {
            session()->forget('face_pending');
            session()->forget('face_tries');
            return response()->json(array("ok" => false, "redirect" => route('login'),
                "message" => "The face did not match 3 times. Please sign in again."));
        }

        $message = $id_message != "" ? $id_message : "The face did not match. Try again in good light.";
        return response()->json(array("ok" => false, "redirect" => "",
            "message" => $message . " (" . (3 - $tries) . " tries left)"));
    }

    // ---------- register a face (Owner / Admin) ----------

    public function enrollForm(Request $request)
    {
        $id = (int) $request->query('id', 0);
        $rows = Proc::call('sp_get_user', array($id));
        if (count($rows) == 0) {
            return redirect()->route('users')->with('flash', array("type" => "error", "text" => "User not found."));
        }
        $user = $rows[0];

        $has_face = false;
        foreach (Proc::call('sp_face_users') as $f) {
            if ($f['user_id'] == $id) {
                $has_face = true;
            }
        }

        $models_url = asset('models');
        $page_title = "Register Face";
        $page_subtitle = "Face login for " . $user['full_name'];
        $current_page = "users";

        return view('face_enroll', compact('user', 'has_face', 'models_url', 'page_title', 'page_subtitle', 'current_page'));
    }

    // saves the face: the live face must also match the face on the National ID
    public function enrollSave(Request $request)
    {
        $id = (int) $request->input('user_id', 0);
        $live = face_numbers($request->input('descriptor'));
        $on_id = face_numbers($request->input('id_descriptor'));

        if ($request->input('consent') != true) {
            return response()->json(array("ok" => false, "message" => "Please tick the consent box first."));
        }
        if ($live == null || $on_id == null) {
            return response()->json(array("ok" => false, "message" => "The face or the ID was not read. Please do the steps again."));
        }

        $score = face_distance($live, $on_id);
        if ($score >= face_id_limit()) {
            return response()->json(array("ok" => false,
                "message" => "The face on the ID does not match the person in front of the camera (score " . number_format($score, 2) . ", must be below " . number_format(face_id_limit(), 2) . "). Please try again with better light."));
        }

        // only the live face numbers are saved (never the photos)
        $answer = Proc::call('sp_face_save', array($id, json_encode($live), json_encode($on_id), session('user_id'), "matched with ID, score " . number_format($score, 2)));
        if (count($answer) == 0 || $answer[0]['result'] != "ok") {
            return response()->json(array("ok" => false, "message" => "User not found."));
        }

        session()->flash('flash', array("type" => "success", "text" => "Face registered. The user can now sign in with the face check."));
        return response()->json(array("ok" => true, "redirect" => route('users'), "message" => "Face registered (ID match score " . number_format($score, 2) . ")."));
    }

    // the Owner removes a face (the user can then sign in with the password only)
    public function remove(Request $request)
    {
        $id = (int) $request->input('user_id', 0);
        Proc::call('sp_face_remove', array($id, session('user_id')));

        return redirect()->route('users')->with('flash', array("type" => "success", "text" => "Face removed."));
    }
}
