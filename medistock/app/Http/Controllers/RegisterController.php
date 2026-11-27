<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// User registration with ID verification and face matching
// order of the pages: start -> ID upload + scan -> face capture + matching -> personal information -> final result
// everything is saved with stored procedures (sql/registration.sql)
class RegisterController extends Controller
{
    // ---------- small helpers ----------

    // supported ID types (the same list is shown to the user)
    private function idTypes()
    {
        $types = array(
            'philsys' => 'Philippine National ID (PhilSys)',
            'license' => "Driver's License (LTO)",
            'passport' => 'Philippine Passport',
            'umid' => 'UMID'
        );
        // for the demo only: a sample ID made for testing (turn off in .env with ID_ALLOW_TEST=false)
        if (env('ID_ALLOW_TEST', true)) {
            $types['test'] = 'Sample / test ID (demo only)';
        }
        return $types;
    }

    // the ID number must look like the chosen ID type (spaces and dashes are ignored)
    private function idNumberOk($type, $number)
    {
        $plain = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $number));
        $patterns = array(
            'philsys' => '/^\d{16}$/',
            'license' => '/^[A-Z]\d{11}$/',
            'passport' => '/^[A-Z]{1,2}\d{7}[A-Z]?$/',
            'umid' => '/^\d{12}$/',
            'test' => '/^[A-Z0-9]{6,20}$/'
        );
        return isset($patterns[$type]) && preg_match($patterns[$type], $plain) == 1;
    }

    private function plainNumber($number)
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $number));
    }

    // run a stored procedure and return the rows
    private function run($name, $params = array())
    {
        $marks = implode(',', array_fill(0, count($params), '?'));
        $stmt = DB::getPdo()->prepare("CALL $name($marks)");
        $stmt->execute($params);
        $rows = array();
        if ($stmt->columnCount() > 0) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        while ($stmt->nextRowset()) {
        }
        $stmt->closeCursor();
        return $rows;
    }

    private function logAttempt($step, $result, $note, $request)
    {
        $this->run('sp_reg_log', array($step, $result, substr($note, 0, 250), $request->ip()));
    }

    // 128 numbers?
    private function isDescriptor($list)
    {
        if (!is_array($list) || count($list) != 128) {
            return false;
        }
        foreach ($list as $n) {
            if (!is_numeric($n)) {
                return false;
            }
        }
        return true;
    }

    private function distance($a, $b)
    {
        $sum = 0;
        for ($i = 0; $i < 128; $i++) {
            $d = $a[$i] - $b[$i];
            $sum += $d * $d;
        }
        return sqrt($sum);
    }

    // how strict the face match is (same settings as the face login if they exist in helpers.php)
    private function faceLimit()
    {
        if (function_exists('face_id_limit')) {
            return face_id_limit();
        }
        return 0.55;
    }

    // the pages must be opened in order
    private function needStep($step)
    {
        $reg = session('reg', array());
        if (!isset($reg['consent_at'])) {
            return redirect()->route('register')->with('reg_notice', 'Please read and accept the consent first.');
        }
        if ($step >= 2 && empty($reg['id_ok'])) {
            return redirect()->route('register.id')->with('reg_notice', 'Scan your ID first. The next steps stay locked until the ID is accepted.');
        }
        if ($step >= 3 && empty($reg['face_ok'])) {
            return redirect()->route('register.face')->with('reg_notice', 'Face matching must be successful first. Personal information stays locked until then.');
        }
        return null;
    }

    // ---------- page 0: start + consent ----------
    public function start()
    {
        return view('register.start', array('types' => $this->idTypes()));
    }

    public function consent(Request $request)
    {
        if (!array_key_exists($request->input('id_type'), $this->idTypes())) {
            return redirect()->route('register')->with('reg_notice', 'Please choose the kind of ID first.');
        }
        if (!$request->has('agree')) {
            return redirect()->route('register')->with('reg_notice', 'The person being added must agree to the consent to continue.');
        }
        session(array('reg' => array('consent_at' => date('Y-m-d H:i:s'), 'id_type_choice' => $request->input('id_type'))));
        return redirect()->route('register.id');
    }

    public function reset()
    {
        session()->forget('reg');
        return redirect()->route('register');
    }

    // ---------- page 1: ID upload + scan ----------
    public function idPage()
    {
        $block = $this->needStep(1);
        if ($block) {
            return $block;
        }
        return view('register.id', array('type' => session('reg.id_type_choice'), 'type_name' => $this->idTypes()[session('reg.id_type_choice')], 'models_url' => asset('models')));
    }

    // the browser sends the text it read and the face numbers of the ID photo (not the picture)
    public function idSave(Request $request)
    {
        if (!session()->has('reg.consent_at')) {
            return response()->json(array('ok' => false, 'message' => 'Session expired. Please start again.'));
        }

        $type = session('reg.id_type_choice');   // the kind of ID chosen in step 2
        $name = trim($request->input('name', ''));
        $dob = $request->input('dob', '');
        $address = trim($request->input('address', ''));
        $number = trim($request->input('id_number', ''));
        $expiry = '';   // expiration date is not used
        $preEdited = max(0, (int) $request->input('edited', 0));
        $conf = (int) $request->input('ocr_confidence', 0);
        $descriptor = $request->input('id_descriptor');

        $fail = function ($message) use ($request) {
            $this->logAttempt('ID scan', 'Failed', $message, $request);
            return response()->json(array('ok' => false, 'message' => $message . ' Please upload the ID again.'));
        };

        if (!array_key_exists($type, $this->idTypes())) {
            return $fail('This ID type is not supported.');
        }
        if (strlen($name) < 5 || preg_match('/\d/', $name)) {
            return $fail('The full name on the ID could not be read correctly.');
        }
        $dobTime = strtotime($dob);
        if ($dob == '' || $dobTime === false || date('Y-m-d', $dobTime) != $dob) {
            return $fail('The date of birth on the ID could not be read.');
        }
        if (!$this->idNumberOk($type, $number)) {
            return $fail('The ID number does not look like a ' . $this->idTypes()[$type] . ' number.');
        }
        if (!$this->isDescriptor($descriptor)) {
            return $fail('The photo on the ID was not detected.');
        }
        if ($conf < 40) {
            return $fail('The ID picture is too blurry.');
        }

        session(array('reg' => array_merge(session('reg'), array(
            'id_ok' => true, 'face_ok' => false, 'tries' => 0,
            'id_type' => $type, 'name' => strtoupper($name), 'dob' => $dob, 'address' => strtoupper($address),
            'id_number' => strtoupper($number), 'expiry' => $expiry, 'ocr' => $conf, 'pre_edited' => $preEdited,
            'id_descriptor' => array_map('floatval', $descriptor)
        ))));

        $this->logAttempt('ID scan', 'Success', 'ID type ' . $type . ', OCR ' . $conf . '%', $request);
        return response()->json(array('ok' => true, 'next' => route('register.face')));
    }

    // ---------- page 2: face capture + matching ----------
    public function facePage()
    {
        $block = $this->needStep(2);
        if ($block) {
            return $block;
        }
        return view('register.face', array('models_url' => asset('models')));
    }

    public function faceMatch(Request $request)
    {
        $reg = session('reg', array());
        if (empty($reg['id_ok'])) {
            return response()->json(array('ok' => false, 'message' => 'Scan your ID first.', 'restart_id' => true));
        }

        $faces = $request->input('faces', array());
        $poses = array('straight', 'left', 'right', 'closer', 'farther');
        foreach ($poses as $pose) {
            if (!isset($faces[$pose]) || !$this->isDescriptor($faces[$pose])) {
                return response()->json(array('ok' => false, 'message' => 'The face capture is not complete or not clear. Please capture again.'));
            }
        }

        $reg['tries'] = (isset($reg['tries']) ? $reg['tries'] : 0) + 1;
        $idFace = $reg['id_descriptor'];
        $limit = $this->faceLimit();

        // 1. is it the same person in all five poses? (nobody swapped places in the middle)
        $same = true;
        $straight = array_map('floatval', $faces['straight']);
        foreach (array('closer' => 0.50, 'farther' => 0.50, 'left' => 0.65, 'right' => 0.65) as $pose => $max) {
            if ($this->distance($straight, array_map('floatval', $faces[$pose])) > $max) {
                $same = false;
            }
        }

        // 2. compare the three front-facing captures with the face on the ID
        $scores = array();
        $mean = array_fill(0, 128, 0);
        foreach (array('straight', 'closer', 'farther') as $pose) {
            $d = array_map('floatval', $faces[$pose]);
            $scores[] = $this->distance($d, $idFace);
            for ($i = 0; $i < 128; $i++) {
                $mean[$i] += $d[$i] / 3;
            }
        }
        $score = round(array_sum($scores) / count($scores), 3);

        $restartId = ($reg['tries'] >= 5);
        $message = '';

        if (!$same) {
            $message = 'The face changed during the capture. Please do all the poses again with the same person.';
        } elseif ($score > $limit) {
            $message = 'The face does not match the photo on the ID (score ' . $score . ', must be ' . $limit . ' or lower). Try again with better light, or upload a clearer ID.';
        }

        if ($message != '') {
            if ($restartId) {
                $message .= ' Too many tries: please upload the ID again.';
            }
            session(array('reg' => $reg));
            $this->logAttempt('Face match', 'Failed', 'score ' . $score . ' (try ' . $reg['tries'] . ')', $request);
            return response()->json(array('ok' => false, 'message' => $message, 'restart_id' => $restartId));
        }

        $reg['face_ok'] = true;
        $reg['face_score'] = $score;
        $reg['face_descriptor'] = array_map(function ($n) {
            return round($n, 6);
        }, $mean);
        session(array('reg' => $reg));

        $this->logAttempt('Face match', 'Success', 'score ' . $score, $request);
        return response()->json(array(
            'ok' => true,
            'message' => 'Face matched with the ID (score ' . $score . ', limit ' . $limit . '). Opening the personal information page...',
            'next' => route('register.info')
        ));
    }

    // ---------- page 3: personal information (locked until ID + face are OK) ----------
    public function infoPage()
    {
        $block = $this->needStep(3);
        if ($block) {
            return $block;
        }
        $reg = session('reg');
        return view('register.info', array(
            'reg' => $reg,
            'type_name' => $this->idTypes()[$reg['id_type']],
            'age' => $this->ageOf($reg['dob'])
        ));
    }

    private function ageOf($dob)
    {
        $birth = new \DateTime($dob);
        return $birth->diff(new \DateTime('today'))->y;
    }

    public function infoSave(Request $request)
    {
        $block = $this->needStep(3);
        if ($block) {
            return $block;
        }
        $reg = session('reg');

        $request->validate(array(
            'full_name' => 'required|string|min:5|max:120',
            'birth_date' => 'required|date|before:today',
            'address' => 'required|string|min:6|max:255',
            'id_number' => 'required|string|max:30',
            'role' => 'required|in:Admin,Staff',
            'username' => 'required|alpha_dash|min:4|max:50',
            'email' => 'required|email|max:100',
            'password' => 'required|min:8|confirmed',
            'confirm_info' => 'accepted'
        ), array(
            'confirm_info.accepted' => 'Please tick the box to confirm that your information is correct.',
            'password.confirmed' => 'The two passwords are not the same.',
            'username.alpha_dash' => 'Username can only have letters, numbers, dashes and underscores.'
        ));

        $age = $this->ageOf($request->input('birth_date'));
        if ($age < 18) {
            return back()->withInput()->withErrors(array('birth_date' => 'You must be at least 18 years old to register.'));
        }
        if (!$this->idNumberOk($reg['id_type'], $request->input('id_number'))) {
            return back()->withInput()->withErrors(array('id_number' => 'This ID number does not look like a valid ' . $this->idTypes()[$reg['id_type']] . ' number.'));
        }

        // count how many fields the user corrected (good for the record)
        $edited = isset($reg['pre_edited']) ? $reg['pre_edited'] : 0;
        $name = strtoupper(trim($request->input('full_name')));
        $address = strtoupper(trim($request->input('address')));
        $plain = $this->plainNumber($request->input('id_number'));
        if ($name != $reg['name']) {
            $edited++;
        }
        if ($request->input('birth_date') != $reg['dob']) {
            $edited++;
        }
        if ($address != $reg['address']) {
            $edited++;
        }
        if ($plain != $this->plainNumber($reg['id_number'])) {
            $edited++;
        }

        // the ID number is saved masked + hashed, never in full
        $hash = hash_hmac('sha256', $reg['id_type'] . ':' . $plain, config('app.key'));
        $masked = str_repeat('*', max(0, strlen($plain) - 4)) . substr($plain, -4);

        $check = $this->run('sp_reg_check', array($request->input('username'), $request->input('email'), $hash));
        $problem = $check[0]['problem'];
        if ($problem == 'USERNAME') {
            return back()->withInput()->withErrors(array('username' => 'This username is already taken.'));
        }
        if ($problem == 'EMAIL') {
            return back()->withInput()->withErrors(array('email' => 'This email is already used.'));
        }
        if ($problem == 'ID') {
            $this->logAttempt('Final', 'Failed', 'ID already registered', $request);
            return back()->withInput()->withErrors(array('id_number' => 'This ID was already used for another registration.'));
        }

        $saved = $this->run('sp_reg_save', array(
            $name, $request->input('birth_date'), $age, $address,
            $reg['id_type'], $masked, $hash, ($reg['expiry'] != '' ? $reg['expiry'] : null),
            $reg['ocr'], $edited, $reg['face_score'], 'Matched',
            json_encode($reg['id_descriptor']), json_encode($reg['face_descriptor']),
            $request->input('username'), $request->input('email'), password_hash($request->input('password'), PASSWORD_DEFAULT),
            $request->input('role')
        ));
        // the admin is adding the user, so the account is created right away
        $decided = $this->run('sp_reg_decide', array((int) $saved[0]['reg_id'], 'Approved', session('username', session('full_name', 'Admin'))));
        // save the face (numbers only) so the new user can use the face login
        if ($decided[0]['message'] == 'OK' && (int) $decided[0]['user_id'] > 0) {
            $this->run('sp_face_save', array(
                (int) $decided[0]['user_id'], json_encode($reg['face_descriptor']), json_encode($reg['id_descriptor']),
                session('user_id'), 'registered with ID scan, match score ' . $reg['face_score']
            ));
        }

        $this->logAttempt('Final', 'Success', 'registration saved for ' . $request->input('username'), $request);

        session()->forget('reg');
        return redirect()->route('register.done')->with('reg_done', array(
            'name' => $name, 'id_type' => $this->idTypes()[$reg['id_type']], 'masked' => $masked,
            'ocr' => $reg['ocr'], 'score' => $reg['face_score'], 'edited' => $edited, 'username' => $request->input('username'), 'role' => $request->input('role')
        ));
    }

    // ---------- page 4: final result ----------
    public function done()
    {
        if (!session()->has('reg_done')) {
            return redirect()->route('register');
        }
        return view('register.done', array('done' => session('reg_done')));
    }
}
