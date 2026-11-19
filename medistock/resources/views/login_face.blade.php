<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediStock - Face Check</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/face.css') }}">
</head>
<body>

    <div class="page">

        <!-- left side (brand panel) -->
        <div class="brand-panel">
            <div class="brand-logo">
                <span class="logo-circle"></span>
                <span class="logo-text">MediStock</span>
            </div>

            <div class="brand-content">
                <h1>One more step to keep the pharmacy safe.</h1>
                <p class="brand-desc">Your password was correct. Now the camera checks that you are really {{ $name }}.</p>

                <ul class="feature-list">
                    <li><span class="dot"></span>Look straight at the camera</li>
                    <li><span class="dot"></span>Turn your head left, then right</li>
                    <li><span class="dot"></span>Your photo is never saved</li>
                </ul>
            </div>

            <p class="copyright">&copy; 2026 MediStock. All rights reserved.</p>
        </div>

        <!-- right side (camera) -->
        <div class="login-panel">
            <div class="login-form">
                <div class="form-heading">
                    <h2>Face check</h2>
                    <p>Hello, {{ $name }}. Press the button and look at the camera.</p>
                </div>

                <div class="cam-box">
                    <video id="cam" muted playsinline></video>
                    <div class="cam-oval"></div>
                </div>

                <p class="cam-message" id="camMessage">Press "Start face check".</p>

                <form onsubmit="return false;">
                    @csrf
                    <button type="button" id="btnStart" class="btn-signin">Start face check</button>
                </form>

                <div class="id-upload" id="idUpload">
                    <span class="alt-text">Choose a clear photo of your ID (straight, no glare):</span>
                    <input type="file" id="idFile" accept="image/*">
                </div>

                <div class="alt-way" id="altWay">
                    <span class="alt-text">Face check not working?</span>
                    <button type="button" id="btnIdMode" class="btn-alt">Use my National ID instead</button>
                </div>

                <p class="back-link"><a href="{{ route('login') }}">Back to login</a></p>
            </div>
        </div>

    </div>

    <script>
        var FACE_PAGE = {
            modelsUrl: "{{ $models_url }}",
            checkUrl: "{{ route('login.face.check') }}"
        };
    </script>
    <script src="{{ asset('js/face-api.min.js') }}"></script>
    <script src="{{ asset('js/face-tools.js') }}"></script>
    <script src="{{ asset('js/face-login.js') }}"></script>
</body>
</html>
