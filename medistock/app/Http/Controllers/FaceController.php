<?php

namespace App\Http\Controllers;

use App\Support\Proc;
use Illuminate\Http\Request;

// Face login (step 2 after the password) and registering a user's face (Owner / Admin only).
// A face is also saved when the Owner / Admin adds a user (RegisterController: ID scan + face matching).
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
        $turned = $request->input('blinked') == true;      // the head was turned left and right

        // the saved face of this user
        $rows = Proc::call('sp_face_get', array((int) $pending['user_id']));
        $saved = count($rows) > 0 ? face_numbers($rows[0]['descriptor']) : null;

        $ok = false;
        $score = 0;
        if ($live != null && $saved != null) {
            $score = face_distance($live, $saved);
            $ok = $score < face_login_limit() && $turned;
        }
        $score_text = number_format($score, 2);

        if ($ok) {
            Proc::call('sp_log_face', array((int) $pending['user_id'], 'Face Login', "Face matched (score " . $score_text . ")"));

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

        return response()->json(array("ok" => false, "redirect" => "",
            "message" => "The face did not match. Try again in good light. (" . (3 - $tries) . " tries left)"));
    }

    // ---------- register a face (Owner / Admin) - face scan only, no ID card ----------

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

    // saves the face (the head turn was checked in the browser)
    public function enrollSave(Request $request)
    {
        $id = (int) $request->input('user_id', 0);
        $live = face_numbers($request->input('descriptor'));

        if ($request->input('consent') != true) {
            return response()->json(array("ok" => false, "message" => "Please tick the consent box first."));
        }
        if ($live == null) {
            return response()->json(array("ok" => false, "message" => "The face was not read. Please do the face scan again."));
        }

        // only the live face numbers are saved (never the photos)
        $answer = Proc::call('sp_face_save', array($id, json_encode($live), null, session('user_id'), "face scan only"));
        if (count($answer) == 0 || $answer[0]['result'] != "ok") {
            return response()->json(array("ok" => false, "message" => "User not found."));
        }

        session()->flash('flash', array("type" => "success", "text" => "Face registered. The user can now sign in with the face check."));
        return response()->json(array("ok" => true, "redirect" => route('users'), "message" => "Face registered."));
    }

    // the Owner removes a face (the user can then sign in with the password only)
    public function remove(Request $request)
    {
        $id = (int) $request->input('user_id', 0);
        Proc::call('sp_face_remove', array($id, session('user_id')));

        return redirect()->route('users')->with('flash', array("type" => "success", "text" => "Face removed."));
    }
}
