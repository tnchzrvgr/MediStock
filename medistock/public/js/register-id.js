// MediStock add new user - steps 3 and 4: upload the ID for scan, then ID info extraction
// (the photo of the ID stays in the browser, only the text fields and the face numbers are sent)
// needs: face-api.min.js, tesseract.min.js, register-parse.js, and the REG object from the page

var fileInput = document.getElementById("idFile");
var typeSelect = document.getElementById("idType");
var preview = document.getElementById("idPreview");
var btnScan = document.getElementById("btnScan");
var btnRetry = document.getElementById("btnRetry");
var btnConfirm = document.getElementById("btnConfirm");
var statusBox = document.getElementById("scanStatus");
var resultBox = document.getElementById("scanResult");
var resultBody = document.getElementById("resultBody");
var idImage = null;          // the loaded picture
var scanning = false;

function showStatus(text, kind) {
    statusBox.className = "notice " + (kind || "info");
    statusBox.textContent = text;
    statusBox.style.display = "block";
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]').getAttribute("content");
}

// ---------- 1. choose the file ----------
fileInput.addEventListener("change", function () {
    resultBox.style.display = "none";
    btnRetry.style.display = "none";
    var file = fileInput.files[0];
    if (!file) { return; }

    if (file.type.indexOf("image/") != 0) {
        showStatus("That file is not a picture. Please upload a JPG or PNG photo of your ID.", "error");
        fileInput.value = "";
        return;
    }
    if (file.size > 8 * 1024 * 1024) {
        showStatus("The picture is too big (more than 8 MB). Please choose a smaller one.", "error");
        fileInput.value = "";
        return;
    }

    var img = new Image();
    img.onload = function () {
        if (img.width < 700 || img.height < 400) {
            showStatus("The picture is too small (" + img.width + "x" + img.height + "). Please upload a clearer, bigger photo of the ID.", "error");
            fileInput.value = "";
            return;
        }
        idImage = img;
        preview.src = img.src;
        preview.style.display = "block";
        btnScan.disabled = false;
        showStatus("Picture received. Press \"Scan ID\".", "info");
    };
    img.onerror = function () { showStatus("The picture cannot be opened. Please try another file.", "error"); };
    img.src = URL.createObjectURL(file);
});

// draw the picture on a canvas (rotated and not bigger than 1600 pixels)
function drawRotated(img, degrees) {
    var scale = Math.min(1, 1600 / Math.max(img.width, img.height));
    var w = Math.round(img.width * scale);
    var h = Math.round(img.height * scale);
    var canvas = document.createElement("canvas");
    var turned = (degrees == 90 || degrees == 270);
    canvas.width = turned ? h : w;
    canvas.height = turned ? w : h;
    var ctx = canvas.getContext("2d");
    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.rotate(degrees * Math.PI / 180);
    ctx.drawImage(img, -w / 2, -h / 2, w, h);
    return canvas;
}

// ---------- 2. find the face on the ID (also tells us how the ID is turned) ----------
async function findIdFace(img) {
    var best = null;
    var angles = [0, 90, 270, 180];
    for (var i = 0; i < angles.length; i++) {
        var canvas = drawRotated(img, angles[i]);
        var found = await faceapi.detectSingleFace(canvas, new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 }))
            .withFaceLandmarks().withFaceDescriptor();
        if (found && (best == null || found.detection.score > best.score)) {
            best = { score: found.detection.score, descriptor: Array.from(found.descriptor), angle: angles[i], box: found.detection.box };
        }
    }
    return best;
}

// ---------- 3. read the text (OCR) ----------

// cut a part of the card, make it bigger, and (mode "red") keep only the red channel:
// blue and black print becomes dark and the light background stays white, so small blue numbers are easy to read
function cropForOcr(canvas, x, y, w, h, mode) {
    x = Math.max(0, Math.round(x)); y = Math.max(0, Math.round(y));
    w = Math.min(canvas.width - x, Math.round(w)); h = Math.min(canvas.height - y, Math.round(h));
    var scale = Math.min(4, Math.max(2, 1400 / Math.max(w, 1)));
    var c = document.createElement("canvas");
    c.width = Math.round(w * scale);
    c.height = Math.round(h * scale);
    var ctx = c.getContext("2d");
    ctx.drawImage(canvas, x, y, w, h, 0, 0, c.width, c.height);
    var img = ctx.getImageData(0, 0, c.width, c.height);
    var px = img.data;
    var low = 255, high = 0, v;
    for (var i = 0; i < px.length; i += 4) {
        v = (mode == "red") ? px[i] : (px[i] * 0.299 + px[i + 1] * 0.587 + px[i + 2] * 0.114);
        px[i] = v;
        if (v < low) { low = v; }
        if (v > high) { high = v; }
    }
    var range = Math.max(40, high - low);
    for (var j = 0; j < px.length; j += 4) {   // stretch the contrast
        v = Math.max(0, Math.min(255, (px[j] - low) * 255 / range));
        px[j] = px[j + 1] = px[j + 2] = v;
    }
    ctx.putImageData(img, 0, 0);
    return c;
}

async function readText(canvas, type, box, progress) {
    var out = { text: "", confidence: 0, name: "", number: "" };

    // 1. the whole card
    progress("reading the whole card...");
    var worker = await Tesseract.createWorker("eng");
    var whole = await worker.recognize(cropForOcr(canvas, 0, 0, canvas.width, canvas.height, "gray"));
    out.text = whole.data.text;
    out.confidence = whole.data.confidence;

    // is this really the kind of ID that was chosen? (stop early if not)
    var typeCheck = checkIdType(out.text, type);
    if (!typeCheck.ok) {
        await worker.terminate();
        out.wrongType = typeCheck.message;
        return out;
    }

    // 2. the PhilSys card has a fixed layout: names are at the right of the photo (big CAPITAL letters)
    if (type == "philsys" && box) {
        progress("reading the name next to the photo...");
        var nameCanvas = cropForOcr(canvas, box.x + box.width * 0.95, box.y - box.height * 0.5, box.width * 1.7, box.height * 2.4, "gray");
        var nameRet = await worker.recognize(nameCanvas);
        out.name = philsysNameFromLines(nameRet.data.text);
    }
    await worker.terminate();

    // 3. the ID number: a second reader that knows only digits (reads the small blue number above the photo)
    if (type == "philsys" || type == "umid") {
        progress("reading the ID number...");
        var digitWorker = await Tesseract.createWorker("eng");
        await digitWorker.setParameters({ tessedit_char_whitelist: "0123456789- ", tessedit_pageseg_mode: "6" });
        var tries = [];
        if (box) {
            tries.push(cropForOcr(canvas, box.x - box.width * 1.0, box.y - box.height * 2.0, box.width * 3.2, box.height * 1.7, "red"));
            tries.push(cropForOcr(canvas, box.x - box.width * 1.0, box.y - box.height * 2.0, box.width * 3.2, box.height * 1.7, "gray"));
        }
        tries.push(cropForOcr(canvas, 0, 0, canvas.width, canvas.height * 0.5, "red"));
        tries.push(cropForOcr(canvas, 0, 0, canvas.width, canvas.height, "red"));
        for (var t = 0; t < tries.length && out.number == ""; t++) {
            var ret = await digitWorker.recognize(tries[t]);
            out.number = numberFromDigits(ret.data.text);
        }
        await digitWorker.terminate();
    }
    return out;
}

// ---------- the table of the information found (the user can type what was not read) ----------
var fieldList = [
    { key: "name", label: "Full name", type: "text", required: true },
    { key: "dob", label: "Date of birth", type: "date", required: true },
    { key: "address", label: "Address", type: "text", required: false },
    { key: "idNumber", label: "ID number", type: "text", required: true }
];
var autoFields = {};         // what the scan found (to count the corrections)

function showFields(fields) {
    autoFields = fields;
    resultBody.innerHTML = "";
    for (var i = 0; i < fieldList.length; i++) {
        var f = fieldList[i];
        var value = fields[f.key] || "";
        var tr = document.createElement("tr");

        var td1 = document.createElement("td");
        td1.textContent = f.label;

        var td2 = document.createElement("td");
        var input = document.createElement("input");
        input.type = f.type;
        input.id = "f_" + f.key;
        input.value = value;
        input.className = "scan-input";
        td2.appendChild(input);

        var td3 = document.createElement("td");
        var badge = document.createElement("span");
        if (value != "") { badge.className = "badge ok"; badge.textContent = "Found"; }
        else if (f.required) { badge.className = "badge bad"; badge.textContent = "Type it"; }
        else { badge.className = "badge warn"; badge.textContent = "Not found"; }
        td3.appendChild(badge);

        tr.appendChild(td1); tr.appendChild(td2); tr.appendChild(td3);
        resultBody.appendChild(tr);
    }
}

var lastFace = null;
var lastConfidence = 0;

// ---------- the scan button ----------
btnScan.addEventListener("click", async function () {
    if (scanning || idImage == null) { return; }
    scanning = true;
    btnScan.disabled = true;
    resultBox.style.display = "none";
    btnConfirm.style.display = "none";
    btnRetry.style.display = "none";
    var type = typeSelect.value;

    try {
        showStatus("Step 1 of 3: loading the face tools (first time can take a while)...", "info");
        await faceapi.nets.ssdMobilenetv1.loadFromUri(REG.modelsUrl);
        await faceapi.nets.faceLandmark68Net.loadFromUri(REG.modelsUrl);
        await faceapi.nets.faceRecognitionNet.loadFromUri(REG.modelsUrl);

        showStatus("Step 2 of 3: looking for the photo on the ID...", "info");
        var face = await findIdFace(idImage);
        if (face == null) {
            throw new Error("NOFACE");
        }

        showStatus("Step 3 of 3: reading the text on the ID (OCR)...", "info");
        var upright = drawRotated(idImage, face.angle);
        var ocr = await readText(upright, type, face.box, function (t) { showStatus("Step 3 of 3: " + t, "info"); });
        if (ocr.wrongType) {
            showStatus(ocr.wrongType, "error");
            btnRetry.style.display = "inline-block";
            scanning = false;
            return;
        }
        var fields = parseIdText(ocr.text, type);

        // the special readings are better than the whole-card reading
        if (ocr.name != "" && nameLooksOk(ocr.name)) { fields.name = ocr.name; }
        else if (!nameLooksOk(fields.name)) { fields.name = ""; }
        if (ocr.number != "") { fields.idNumber = ocr.number; }

        var found = 0;
        if (fields.name != "") { found++; }
        if (fields.dob != "") { found++; }
        if (fields.idNumber != "") { found++; }

        document.getElementById("ocrConf").textContent = Math.round(ocr.confidence) + "%";

        // almost nothing was read: the picture is not good enough
        if (ocr.confidence < 40 || found < 2) {
            showStatus("ID scan unsuccessful. The text is not readable enough. Please upload the ID again (flat, bright light, no glare, all four corners visible).", "error");
            btnRetry.style.display = "inline-block";
            scanning = false;
            return;
        }

        lastFace = face;
        lastConfidence = Math.round(ocr.confidence);
        showFields(fields);
        resultBox.style.display = "block";
        btnConfirm.style.display = "block";
        if (found == 3) {
            showStatus("ID scanned successfully. Check the information below, then press Confirm.", "success");
        } else {
            showStatus("ID scanned, but some information was not read. Type the missing information from your ID, then press Confirm.", "info");
        }
        scanning = false;
    } catch (err) {
        if (err.message == "NOFACE") {
            showStatus("The photo on the ID was not found. Upload the ID again: the whole card, front side, straight and not covered.", "error");
        } else {
            showStatus("Something went wrong while scanning (" + err.message + "). Check your internet (the OCR tool loads online) and try again.", "error");
        }
        btnRetry.style.display = "inline-block";
        scanning = false;
    }
});

// ---------- confirm: send the reviewed information (not the picture) ----------
btnConfirm.addEventListener("click", async function () {
    var values = {};
    var edited = 0;
    for (var i = 0; i < fieldList.length; i++) {
        var f = fieldList[i];
        values[f.key] = document.getElementById("f_" + f.key).value.trim();
        if (values[f.key].toUpperCase() != (autoFields[f.key] || "").toUpperCase()) { edited++; }
        if (f.required && values[f.key] == "") {
            showStatus("Please fill in the " + f.label.toLowerCase() + " (copy it from the ID).", "error");
            return;
        }
    }
    btnConfirm.disabled = true;
    showStatus("Saving the result...", "info");
    try {
        var response = await fetch(REG.idUrl, {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrfToken() },
            body: JSON.stringify({
                name: values.name, dob: values.dob, address: values.address, id_number: values.idNumber,
                edited: edited,
                ocr_confidence: lastConfidence,
                id_descriptor: lastFace.descriptor
            })
        });
        var data = await response.json();
        if (data.ok) {
            showStatus("ID accepted. Going to face recognition...", "success");
            window.location.href = data.next;
        } else {
            showStatus(data.message, "error");
            btnConfirm.disabled = false;
            btnRetry.style.display = "inline-block";
        }
    } catch (err) {
        showStatus("The server did not answer. Please try again.", "error");
        btnConfirm.disabled = false;
    }
});

// ---------- retry ----------
btnRetry.addEventListener("click", function () {
    fileInput.value = "";
    idImage = null;
    preview.style.display = "none";
    resultBox.style.display = "none";
    btnRetry.style.display = "none";
    btnConfirm.style.display = "none";
    btnConfirm.disabled = false;
    btnScan.disabled = true;
    scanning = false;
    statusBox.style.display = "none";
    fileInput.click();
});
