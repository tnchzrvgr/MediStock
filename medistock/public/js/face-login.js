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
