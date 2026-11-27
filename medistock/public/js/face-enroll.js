// Register Face page (resources/views/face_enroll.blade.php)
// Step 1: face scan (look straight, turn the head left and right)   Step 2: consent and save

var video = document.getElementById("cam");
var camMessage = document.getElementById("camMessage");
var liveNumbers = null;     // the live face (128 numbers)
var cameraOn = false;

function say(text, isError) {
    camMessage.textContent = text;
    camMessage.className = "cam-message" + (isError ? " cam-error" : "");
}

function stepDone(number, done) {
    var badge = document.getElementById("step" + number);
    badge.className = "step-badge" + (done ? " step-done" : "");
    badge.textContent = done ? "✓" : number;
}

function updateSaveButton() {
    var ok = liveNumbers != null && document.getElementById("consent").checked;
    document.getElementById("btnSave").disabled = !ok;
}

// ----- step 1: the live face -----
document.getElementById("btnLive").addEventListener("click", async function () {
    var button = this;
    button.disabled = true;
    liveNumbers = null;
    stepDone(1, false);
    try {
        say("Loading the face program...");
        await FaceTools.loadModels(FACE_PAGE.modelsUrl);
        if (!cameraOn) {
            await FaceTools.startCamera(video);
            cameraOn = true;
        }

        var result = await FaceTools.waitForTurn(video, say, 25);

        // take 3 more pictures and use the average (more stable)
        var list = [result.descriptor];
        for (var i = 0; i < 3; i++) {
            var faces = await FaceTools.detectLive(video);
            if (faces.length == 1) {
                list.push(Array.from(faces[0].descriptor));
            }
        }
        liveNumbers = FaceTools.average(list);
        stepDone(1, true);
        say("Face captured. Now tick the consent box and save.");
    } catch (e) {
        say(e.message, true);
    }
    button.disabled = false;
    updateSaveButton();
});

document.getElementById("consent").addEventListener("change", updateSaveButton);

// ----- step 2: save -----
document.getElementById("btnSave").addEventListener("click", async function () {
    var button = this;
    button.disabled = true;
    try {
        var answer = await FaceTools.postJson(FACE_PAGE.saveUrl, {
            user_id: FACE_PAGE.userId,
            descriptor: liveNumbers,
            consent: document.getElementById("consent").checked
        });
        if (answer.ok) {
            FaceTools.stopCamera(video);
            window.location = answer.redirect;
            return;
        }
        say(answer.message, true);
    } catch (e) {
        say(e.message, true);
    }
    updateSaveButton();
});
