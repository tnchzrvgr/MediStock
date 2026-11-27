@extends('register.layout')

@section('active', '5')

@section('content')
<div class="form-heading">
    <h2>Face recognition and identity matching</h2>
    <p>The camera will ask you to do five small poses. Then your face is matched with the photo on your ID.</p>
</div>

<div class="cam-box">
    <video id="cam" muted playsinline></video>
    <div class="cam-oval"></div>
</div>

<p class="cam-message" id="camMessage">Press "Start camera".</p>

<div class="checks">
    <div class="check" id="checkFace"><span>Entire face visible</span></div>
    <div class="check" id="checkLight"><span>Lighting</span></div>
</div>

<ul class="pose-list">
    <li id="step_straight"><img id="thumb_straight" alt=""><span>Look straight at the camera</span></li>
    <li id="step_left"><img id="thumb_left" alt=""><span>Look to the left</span></li>
    <li id="step_right"><img id="thumb_right" alt=""><span>Look to the right</span></li>
    <li id="step_closer"><img id="thumb_closer" alt=""><span>Move closer to the camera</span></li>
    <li id="step_farther"><img id="thumb_farther" alt=""><span>Move slightly farther away</span></li>
</ul>

<div id="matchResult" class="notice info" style="display:none;"></div>

<button type="button" id="btnStart" class="btn-signin">Start camera</button>
<button type="button" id="btnRestart" class="btn-alt" style="display:none; margin-top:10px;">Capture my face again</button>
<a href="{{ route('register.reset') }}" id="linkNewId" class="btn-alt link-btn" style="display:none; margin-top:10px;">Upload a different ID</a>

<p class="small-note">Your pictures stay in this browser. Only the face numbers are sent for matching.</p>
@endsection

@section('scripts')
<script>
    var REG = {
        modelsUrl: "{{ $models_url }}",
        matchUrl: "{{ route('register.face.match') }}"
    };
</script>
<script src="{{ asset('js/face-api.min.js') }}"></script>
<script src="{{ asset('js/register-face.js') }}"></script>
@endsection
