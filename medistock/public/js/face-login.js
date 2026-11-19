// Login step 2: the camera checks the face (page: resources/views/login_face.blade.php)

var video = document.getElementById("cam");
var message = document.getElementById("camMessage");
var button = document.getElementById("btnStart");

function say(text, isError) {
    message.textContent = text;
    message.className = "cam-message" + (isError ? " cam-error" : "");
}

async function startCheck() {
    button.disabled = true;
    button.textContent = "Checking...";
    try {
        say("Loading the face program...");
        await FaceTools.loadModels(FACE_PAGE.modelsUrl);

        say("Opening the camera...");
        await FaceTools.startCamera(video);

        // the person must turn the head left and right (so a photo of a face does not work)
        var result = await FaceTools.waitForTurn(video, say, 25);
        FaceTools.stopCamera(video);
        say("Checking your face...");

        var answer = await FaceTools.postJson(FACE_PAGE.checkUrl, { descriptor: result.descriptor, blinked: true });
        if (answer.ok) {
            say(answer.message);
            window.location = answer.redirect;
            return;
        }
        say(answer.message, true);
        if (answer.redirect != "") {
            setTimeout(function () { window.location = answer.redirect; }, 2500);
            return;
        }
    } catch (e) {
        FaceTools.stopCamera(video);
        say(e.message, true);
    }
    button.disabled = false;
    button.textContent = "Try again";
}

button.addEventListener("click", startCheck);

// ---------- the other way: scan the National ID instead of the head turn ----------
// step 1: capture the face (no head turn needed)   step 2: scan the ID, the face on the ID must be the same person
var idMode = false;
var idStep = 0;
var liveNumbers = null;

// reads the face on the ID picture (canvas or image) and sends everything to the server
async function checkIdPicture(source) {
    button.disabled = true;
    document.getElementById("idFile").disabled = true;
    try {
        await FaceTools.loadModels(FACE_PAGE.modelsUrl);
        say("Reading the ID...");
        var found = await FaceTools.detectOnId(source);
        if (found == null) {
            throw new Error("No face found on the ID. Use a clear, straight photo of the ID (no glare), then try again.");
        }

        FaceTools.stopCamera(video);
        say("Checking your face and ID...");
        var answer = await FaceTools.postJson(FACE_PAGE.checkUrl, { descriptor: liveNumbers, id_descriptor: found.descriptor, blinked: false });
        if (answer.ok) {
            say(answer.message);
            window.location = answer.redirect;
            return;
        }
        say(answer.message, true);
        if (answer.redirect != "") {
            setTimeout(function () { window.location = answer.redirect; }, 2500);
            return;
        }
        // wrong: start the ID way again (the face is captured again)
        idStep = 0;
        button.textContent = "Capture my face";
        document.getElementById("idUpload").style.display = "none";
    } catch (e) {
        say(e.message, true);
    }
    button.disabled = false;
    document.getElementById("idFile").disabled = false;
    document.getElementById("idFile").value = "";
}

// the user chose a photo file of the ID
document.getElementById("idFile").addEventListener("change", function () {
    var file = this.files[0];
    if (!file || liveNumbers == null) {
        return;
    }
    var image = new Image();
    image.onload = function () {
        checkIdPicture(image);
    };
    image.src = URL.createObjectURL(file);
});

async function idModeNext() {
    button.disabled = true;
    try {
        await FaceTools.loadModels(FACE_PAGE.modelsUrl);
        if (video.srcObject == null) {
            say("Opening the camera...");
            await FaceTools.startCamera(video);
        }

        if (idStep == 0) {
            // capture the live face (3 pictures, average)
            say("Look straight at the camera...");
            var list = [];
            var start = Date.now();
            while (list.length < 3 && Date.now() - start < 15000) {
                var faces = await FaceTools.detectLive(video);
                if (faces.length == 1 && faces[0].detection.box.width >= 110) {
                    list.push(Array.from(faces[0].descriptor));
                } else {
                    say(faces.length > 1 ? "Only one person please." : "Look straight at the camera, a little closer.");
                }
            }
            if (list.length < 3) {
                throw new Error("No face was found. Face the light and try again.");
            }
            liveNumbers = FaceTools.average(list);
            idStep = 1;
            say("Face captured. Choose a clear photo of your ID below (best), or hold the ID close to the camera and press the button.");
            button.textContent = "Scan my ID with the camera";
            document.getElementById("idUpload").style.display = "block";
        } else {
            // scan the ID with the camera
            var canvas = document.createElement("canvas");
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext("2d").drawImage(video, 0, 0);
            await checkIdPicture(canvas);
            return;
        }
    } catch (e) {
        say(e.message, true);
    }
    button.disabled = false;
}

document.getElementById("btnIdMode").addEventListener("click", function (event) {
    event.preventDefault();
    idMode = true;
    idStep = 0;
    FaceTools.stopCamera(video);
    button.removeEventListener("click", startCheck);
    button.addEventListener("click", idModeNext);
    button.disabled = false;
    button.textContent = "Capture my face";
    say("National ID way: press the button, look at the camera, then show your ID.");
    document.getElementById("altWay").style.display = "none";
});
