// Face tools for MediStock (uses the face-api.js library)
// Used by the login face page and by the "Register Face" page.
// The browser only makes 128 numbers from the face. The server decides if it matches.

var FaceTools = {

    // loads the library models one time (files are in public/models)
    loadModels: async function (modelsUrl) {
        if (typeof faceapi === "undefined") {
            throw new Error("The file public/js/face-api.min.js is missing. Please read public/js/README.txt.");
        }
        try {
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(modelsUrl),
                faceapi.nets.ssdMobilenetv1.loadFromUri(modelsUrl),
                faceapi.nets.faceLandmark68Net.loadFromUri(modelsUrl),
                faceapi.nets.faceRecognitionNet.loadFromUri(modelsUrl)
            ]);
        } catch (e) {
            throw new Error("The model files in public/models are missing. Please read public/models/README.txt.");
        }
        this.liveOptions = new faceapi.TinyFaceDetectorOptions({ inputSize: 256, scoreThreshold: 0.5 });
        this.idOptions = new faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 });
    },

    startCamera: async function (video) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error("This browser cannot use the camera. Use Chrome or Edge on http://localhost:8000 (or https).");
        }
        var stream;
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480, facingMode: "user" }, audio: false });
        } catch (e) {
            throw new Error("The camera could not be opened. Please allow the camera in the browser.");
        }
        video.srcObject = stream;
        await video.play();
    },

    stopCamera: function (video) {
        if (video.srcObject) {
            video.srcObject.getTracks().forEach(function (track) { track.stop(); });
            video.srcObject = null;
        }
    },

    // all faces now in the video (every face has landmarks and the 128 numbers)
    detectLive: async function (video) {
        return await faceapi.detectAllFaces(video, this.liveOptions).withFaceLandmarks().withFaceDescriptors();
    },

    // draws the picture on a canvas turned by 0 / 90 / 180 / 270 degrees (and not bigger than 1000 pixels, so it is fast)
    turnToCanvas: function (source, angle) {
        var width = source.naturalWidth || source.videoWidth || source.width;
        var height = source.naturalHeight || source.videoHeight || source.height;
        var scale = Math.min(1, 1000 / Math.max(width, height));
        width = Math.round(width * scale);
        height = Math.round(height * scale);

        var canvas = document.createElement("canvas");
        var sideways = (angle == 90 || angle == 270);
        canvas.width = sideways ? height : width;
        canvas.height = sideways ? width : height;

        var context = canvas.getContext("2d");
        context.translate(canvas.width / 2, canvas.height / 2);
        context.rotate(angle * Math.PI / 180);
        context.drawImage(source, -width / 2, -height / 2, width, height);
        return canvas;
    },

    // the face on a photo of the ID (image or canvas).
    // The photo may be sideways or upside down, so the 4 directions are tried and the best one is used.
    // Then the small ID photo is cut out and enlarged, so the face numbers are better.
    // Gives { descriptor: 128 numbers, preview: picture (upright) } or null when there is no face.
    detectOnId: async function (source) {
        var options = new faceapi.SsdMobilenetv1Options({ minConfidence: 0.3 });
        var best = null;

        for (var angle = 0; angle < 360; angle += 90) {
            var canvas = this.turnToCanvas(source, angle);
            var found = await faceapi.detectAllFaces(canvas, options).withFaceLandmarks().withFaceDescriptors();

            for (var i = 0; i < found.length; i++) {
                if (best == null || found[i].detection.score > best.face.detection.score) {
                    best = { face: found[i], canvas: canvas };
                }
            }
            // a clear face was found in this direction: no need to try the others
            if (best != null && best.face.detection.score >= 0.9) {
                break;
            }
        }

        if (best == null) {
            return null;
        }

        // cut out the face with some space around it and make it bigger (ID photos are small)
        var box = best.face.detection.box;
        var size = Math.max(box.width, box.height) * 1.8;
        var x = Math.max(0, box.x + box.width / 2 - size / 2);
        var y = Math.max(0, box.y + box.height / 2 - size / 2);
        var crop = document.createElement("canvas");
        crop.width = 320;
        crop.height = 320;
        crop.getContext("2d").drawImage(best.canvas, x, y, size, size, 0, 0, 320, 320);

        var descriptor = best.face.descriptor;
        var again = await faceapi.detectSingleFace(crop, options).withFaceLandmarks().withFaceDescriptor();
        if (again) {
            descriptor = again.descriptor;
        }

        return { descriptor: Array.from(descriptor), preview: best.canvas.toDataURL("image/jpeg", 0.8) };
    },

    // where the head points: the nose tip between the two sides of the face.
    // about 0.50 = looking straight, bigger = turned to the person's left, smaller = turned to the right
    // (the camera picture is not mirrored; only the screen shows it like a mirror)
    turnRatio: function (points) {
        var nose = points[30];
        var leftEdge = points[0];
        var rightEdge = points[16];
        return (nose.x - leftEdge.x) / (rightEdge.x - leftEdge.x);
    },

    // look left and right check: straight -> one side -> the other side -> straight again
    // state.step: 0 = learning straight face, 1 = waiting for the first side, 2 = waiting for the other side,
    //             3 = waiting for the face to look straight again, 4 = done
    // state.first = "left" or "right" (the side that came first)
    turnStep: function (state, ratio) {
        var left = ratio > 0.60;
        var right = ratio < 0.40;
        var straight = ratio >= 0.44 && ratio <= 0.56;

        if (state.count === undefined) {
            state.count = 0;
        }

        if (state.step == 0) {
            // two frames looking straight
            state.count = straight ? state.count + 1 : 0;
            if (state.count >= 2) {
                state.step = 1;
            }
        } else if (state.step == 1) {
            if (left || right) {
                state.first = left ? "left" : "right";
                state.step = 2;
            }
        } else if (state.step == 2) {
            // the other side
            if ((state.first == "left" && right) || (state.first == "right" && left)) {
                state.step = 3;
            }
        } else if (state.step == 3) {
            if (straight) {
                state.step = 4;
            }
        }
        return state;
    },

    // waits until the person looks to the left and to the right. Gives { descriptor, box } of a face looking straight.
    // say(text) shows a message to the user. Stops with an error after "seconds".
    // (only the landmarks are made in the loop, so it is fast; the 128 numbers are made one time at the end)
    waitForTurn: async function (video, say, seconds) {
        var state = { step: 0 };
        var start = Date.now();

        while (Date.now() - start < seconds * 1000) {
            var faces = await faceapi.detectAllFaces(video, this.liveOptions).withFaceLandmarks();

            if (faces.length == 0) {
                say("No face found. Look at the camera.");
                continue;
            }
            if (faces.length > 1) {
                say("Only one person please.");
                continue;
            }
            if (faces[0].detection.box.width < 110) {
                say("Come a little closer to the camera.");
                continue;
            }

            var ratio = this.turnRatio(faces[0].landmarks.positions);
            this.turnStep(state, ratio);

            if (state.step == 0) {
                say("Look straight at the camera.");
            } else if (state.step == 1) {
                say("Slowly turn your head to the LEFT.");
            } else if (state.step == 2) {
                say(state.first == "left" ? "Good. Now slowly turn to the RIGHT." : "Good. Now slowly turn to the LEFT.");
            } else if (state.step == 3) {
                say("Good. Now look straight at the camera.");
            } else {
                // done: make the face numbers from this straight face
                var clear = await this.detectLive(video);
                if (clear.length == 1) {
                    return { descriptor: Array.from(clear[0].descriptor), box: clear[0].detection.box };
                }
            }
        }
        throw new Error("The head turn was not seen. Please try again: face the light and turn your head slowly to the left and then to the right.");
    },

    // the average of some descriptors (more stable than one picture)
    average: function (list) {
        var result = new Array(128).fill(0);
        for (var i = 0; i < list.length; i++) {
            for (var j = 0; j < 128; j++) {
                result[j] += list[i][j] / list.length;
            }
        }
        return result;
    },

    // sends JSON to the server with the Laravel CSRF token
    postJson: async function (url, data) {
        var token = document.querySelector("input[name=_token]").value;
        var response = await fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": token },
            body: JSON.stringify(data)
        });
        if (!response.ok) {
            throw new Error("The server gave an error (" + response.status + "). Please sign in again.");
        }
        return await response.json();
    }
};
