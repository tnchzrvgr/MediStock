// Register Face page (resources/views/face_enroll.blade.php)
// Step 1: live face + head turn   Step 2: face on the National ID   Step 3: consent and save

var video = document.getElementById("cam");
var camMessage = document.getElementById("camMessage");
var liveNumbers = null;     // the live face (128 numbers)
var idNumbers = null;       // the face on the ID (128 numbers)
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
    var ok = liveNumbers != null && idNumbers != null && document.getElementById("consent").checked;
    document.getElementById("btnSave").disabled = !ok;
}

async function openCamera() {
    await FaceTools.loadModels(FACE_PAGE.modelsUrl);
    if (!cameraOn) {
        await FaceTools.startCamera(video);
        cameraOn = true;
    }
}

// ----- step 1: the live face -----
document.getElementById("btnLive").addEventListener("click", async function () {
    var button = this;
    button.disabled = true;
    liveNumbers = null;
    stepDone(1, false);
    try {
        say("Loading the face program...");
        await openCamera();

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
        say("Live face captured. Now do step 2.");
    } catch (e) {
        say(e.message, true);
    }
    button.disabled = false;
    updateSaveButton();
});

// ----- step 2: the National ID -----
async function readIdFrom(source, preview) {
    idNumbers = null;
    stepDone(2, false);
    try {
        await FaceTools.loadModels(FACE_PAGE.modelsUrl);
        say("Looking for the face on the ID...");
        var result = await FaceTools.detectOnId(source);
        if (result == null) {
            say("No face was found on the ID. Hold the card flat, in good light, with the photo clear.", true);
        } else {
            idNumbers = result.descriptor;
            // show the ID turned upright (the way the program read it)
            document.getElementById("idPreview").src = result.preview;
            document.getElementById("idPreview").style.display = "block";
            stepDone(2, true);
            say("The face on the ID was found.");
        }
    } catch (e) {
        say(e.message, true);
    }
    updateSaveButton();
}

// scan with the camera: hold the ID in front of the camera, then press the button
document.getElementById("btnIdCamera").addEventListener("click", async function () {
    try {
        await openCamera();
    } catch (e) {
        say(e.message, true);
        return;
    }
    var canvas = document.createElement("canvas");
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext("2d").drawImage(video, 0, 0);
    await readIdFrom(canvas, canvas.toDataURL("image/jpeg", 0.8));
});

// or choose a photo of the ID from the computer
document.getElementById("idFile").addEventListener("change", function () {
    var file = this.files[0];
    if (!file) {
        return;
    }
    var url = URL.createObjectURL(file);
    var image = new Image();
    image.onload = function () {
        readIdFrom(image, url);
    };
    image.src = url;
});

document.getElementById("consent").addEventListener("change", updateSaveButton);

// ----- step 3: save -----
document.getElementById("btnSave").addEventListener("click", async function () {
    var button = this;
    button.disabled = true;
    try {
        var answer = await FaceTools.postJson(FACE_PAGE.saveUrl, {
            user_id: FACE_PAGE.userId,
            descriptor: liveNumbers,
            id_descriptor: idNumbers,
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
