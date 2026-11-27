// MediStock registration - step 3 and 4: guided face capture, then matching with the ID
// the pictures stay in the browser (only the 128 face numbers of each pose are sent)
// needs: face-api.min.js and the REG object from the page

var video = document.getElementById("cam");
var message = document.getElementById("camMessage");
var btnStart = document.getElementById("btnStart");
var btnRestart = document.getElementById("btnRestart");
var checkFace = document.getElementById("checkFace");
var checkLight = document.getElementById("checkLight");
var resultBox = document.getElementById("matchResult");

// the poses we ask for (same list as the guidelines)
var STEPS = [
    { key: "straight", text: "Look straight at the camera" },
    { key: "left", text: "Slowly turn your head to the left" },
    { key: "right", text: "Slowly turn your head to the right" },
    { key: "closer", text: "Move a little closer to the camera" },
    { key: "farther", text: "Move slightly farther away" }
];

var stepNow = 0;
var goodFrames = 0;
var captured = {};         // key -> descriptor numbers
var baseWidth = 0;         // face size at the "straight" step
var timer = null;
var busy = false;
var stepStart = 0;
var running = false;

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute("content");
}

function setMessage(text, kind) {
    message.textContent = text;
    message.className = "cam-message " + (kind || "");
}

function markCheck(el, ok, text) {
    el.className = "check " + (ok ? "ok" : "bad");
    el.querySelector("span").textContent = text;
}

function showSteps() {
    for (var i = 0; i < STEPS.length; i++) {
        var li = document.getElementById("step_" + STEPS[i].key);
        li.className = i < stepNow ? "done" : (i == stepNow ? "now" : "");
    }
}

// average brightness of the face area (0 = black, 255 = white)
function brightness(box) {
    var c = document.createElement("canvas");
    c.width = 32; c.height = 32;
    var ctx = c.getContext("2d");
    ctx.drawImage(video, Math.max(0, box.x), Math.max(0, box.y), box.width, box.height, 0, 0, 32, 32);
    var px = ctx.getImageData(0, 0, 32, 32).data;
    var total = 0;
    for (var i = 0; i < px.length; i += 4) { total += (px[i] * 0.299 + px[i + 1] * 0.587 + px[i + 2] * 0.114); }
    return total / (px.length / 4);
}

// small snapshot of the face for the list (not sent to the server)
function thumb(box) {
    var c = document.createElement("canvas");
    c.width = 64; c.height = 64;
    c.getContext("2d").drawImage(video, Math.max(0, box.x), Math.max(0, box.y), box.width, box.height, 0, 0, 64, 64);
    return c.toDataURL("image/jpeg", 0.6);
}

// 0.5 = nose in the middle. Bigger = head turned to the person's left, smaller = to the right
function turnRatio(points) {
    var nose = points[30].x, edgeA = points[0].x, edgeB = points[16].x;
    return (nose - edgeA) / (edgeB - edgeA);
}

// does the face fit the pose we asked for? returns "" when OK, or a hint
function checkPose(key, ratio, widthRatio) {
    if (key == "straight") {
        if (ratio < 0.43 || ratio > 0.57) { return "Face the camera straight."; }
        if (widthRatio < 0.18) { return "Move a little closer."; }
    }
    if (key == "left" && ratio < 0.60) { return "Turn your head more to the left (keep your eyes open)."; }
    if (key == "right" && ratio > 0.40) { return "Turn your head more to the right."; }
    if (key == "closer") {
        if (ratio < 0.35 || ratio > 0.65) { return "Face the camera while you move closer."; }
        // a little bigger than the first pose is enough (or already a big face)
        if (widthRatio < baseWidth * 1.12 && widthRatio < 0.45) { return "A little closer, please."; }
    }
    if (key == "farther") {
        if (ratio < 0.40 || ratio > 0.60) { return "Face the camera while you move back."; }
        if (widthRatio > baseWidth * 0.88) { return "Move back a little more."; }
        if (widthRatio < 0.10) { return "Too far, move a little closer."; }
    }
    return "";
}

async function tick() {
    if (busy || !running) { return; }
    busy = true;
    try {
        var options = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.5 });
        var det = await faceapi.detectSingleFace(video, options).withFaceLandmarks();

        if (!det) {
            goodFrames = 0;
            markCheck(checkFace, false, "No face found");
            setMessage("No face found. Face the camera and take off mask, cap or sunglasses.", "warn");
            busy = false;
            return;
        }

        var box = det.detection.box;
        var vw = video.videoWidth, vh = video.videoHeight;
        var margin = (STEPS[stepNow].key == "closer") ? 0.0 : 0.02;   // a little less strict while moving closer
        var whole = (box.x > vw * margin && box.y > vh * margin && box.x + box.width < vw * (1 - margin) && box.y + box.height < vh * (1 - margin));
        if (!whole) {
            goodFrames = 0;
            markCheck(checkFace, false, "Face is cut off");
            setMessage("Keep your entire face inside the picture.", "warn");
            busy = false;
            return;
        }
        markCheck(checkFace, true, "Entire face visible");

        var light = brightness(box);
        if (light < 55) {
            goodFrames = 0;
            markCheck(checkLight, false, "Too dark");
            setMessage("Too dark. Face a window or turn on a light.", "warn");
            busy = false;
            return;
        }
        if (light > 215) {
            goodFrames = 0;
            markCheck(checkLight, false, "Too bright");
            setMessage("Too bright. Move away from strong light behind or on your face.", "warn");
            busy = false;
            return;
        }
        markCheck(checkLight, true, "Lighting is good");

        var ratio = turnRatio(det.landmarks.positions);
        var widthRatio = box.width / vw;
        var step = STEPS[stepNow];
        var hint = checkPose(step.key, ratio, widthRatio);

        if (hint != "") {
            goodFrames = 0;
            setMessage(step.text + ". " + hint, "");
            if (Date.now() - stepStart > 30000) { setMessage("Taking long? Press \"Start again\" and check light and camera. " + hint, "warn"); }
            busy = false;
            return;
        }

        goodFrames++;
        setMessage(step.text + " - hold still...", "ok");
        if (goodFrames >= 4) {
            // save this pose (face numbers + a small picture for the list)
            var full = await faceapi.detectSingleFace(video, options).withFaceLandmarks().withFaceDescriptor();
            if (full) {
                captured[step.key] = Array.from(full.descriptor);
                if (step.key == "straight") { baseWidth = widthRatio; }
                document.getElementById("thumb_" + step.key).src = thumb(box);
                stepNow++;
                goodFrames = 0;
                stepStart = Date.now();
                showSteps();
                if (stepNow >= STEPS.length) {
                    running = false;
                    clearInterval(timer);
                    await sendToServer();
                    busy = false;
                    return;
                }
                setMessage("Good! Next: " + STEPS[stepNow].text, "ok");
            }
        }
    } catch (err) {
        setMessage("Face tool error: " + err.message, "warn");
    }
    busy = false;
}

async function sendToServer() {
    setMessage("Face capture complete. Matching your face with the ID...", "ok");
    video.pause();
    try {
        var response = await fetch(REG.matchUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrfToken() },
            body: JSON.stringify({ faces: captured })
        });
        var data = await response.json();
        resultBox.style.display = "block";
        resultBox.className = "notice " + (data.ok ? "success" : "error");
        resultBox.textContent = data.message;
        if (data.ok) {
            setMessage("Face matches the ID.", "ok");
            setTimeout(function () { window.location.href = data.next; }, 1500);
        } else {
            setMessage("Not matched. You can try again.", "warn");
            btnRestart.style.display = "inline-block";
            if (data.restart_id) {
                document.getElementById("linkNewId").style.display = "inline-block";
            }
        }
    } catch (err) {
        resultBox.style.display = "block";
        resultBox.className = "notice error";
        resultBox.textContent = "The server did not answer. Please try again.";
        btnRestart.style.display = "inline-block";
    }
}

function restart() {
    captured = {};
    stepNow = 0;
    goodFrames = 0;
    baseWidth = 0;
    stepStart = Date.now();
    resultBox.style.display = "none";
    btnRestart.style.display = "none";
    for (var i = 0; i < STEPS.length; i++) { document.getElementById("thumb_" + STEPS[i].key).removeAttribute("src"); }
    showSteps();
    video.play();
    running = true;
    clearInterval(timer);
    timer = setInterval(tick, 250);
    setMessage(STEPS[0].text, "");
}

btnStart.addEventListener("click", async function () {
    btnStart.disabled = true;
    setMessage("Loading the face tools...", "");
    try {
        await faceapi.nets.tinyFaceDetector.loadFromUri(REG.modelsUrl);
        await faceapi.nets.faceLandmark68Net.loadFromUri(REG.modelsUrl);
        await faceapi.nets.faceRecognitionNet.loadFromUri(REG.modelsUrl);
    } catch (err) {
        setMessage("The face models were not found in public/models. See the README in that folder.", "warn");
        btnStart.disabled = false;
        return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        setMessage("The camera needs localhost or https. Open the system with http://localhost:8000.", "warn");
        btnStart.disabled = false;
        return;
    }
    try {
        var stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480, facingMode: "user" }, audio: false });
        video.srcObject = stream;
        await video.play();
    } catch (err) {
        setMessage("The camera cannot be used. Allow camera access in the browser and press the button again.", "warn");
        btnStart.disabled = false;
        return;
    }
    btnStart.style.display = "none";
    restart();
});

btnRestart.addEventListener("click", restart);
showSteps();
