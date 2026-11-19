@extends('layouts.app')

@section('content')
        <link rel="stylesheet" href="{{ asset('css/face.css') }}">

        <div class="face-layout">

            <!-- the camera -->
            <div class="card form-card">
                <h3 class="form-title">Camera</h3>
                <div class="cam-box">
                    <video id="cam" muted playsinline></video>
                    <div class="cam-oval"></div>
                </div>
                <p class="cam-message" id="camMessage">Do the 3 steps on the right.</p>
                <p class="cam-note">The photos are only used to make numbers. They are never saved.</p>
                <form onsubmit="return false;">@csrf</form>
            </div>

            <!-- the steps -->
            <div class="card form-card">
                <h3 class="form-title">{{ $has_face ? 'Update' : 'Register' }} face of {{ $user['full_name'] }}</h3>

                <div class="face-steps">
                    <div class="face-step">
                        <span class="step-badge" id="step1">1</span>
                        <div>
                            <h4>Live face</h4>
                            <p>The user sits in front of the camera, looks straight, then slowly turns the head to the left and to the right when asked.</p>
                            <div class="step-actions">
                                <button type="button" class="btn btn-primary" id="btnLive">Start camera</button>
                            </div>
                        </div>
                    </div>

                    <div class="face-step">
                        <span class="step-badge" id="step2">2</span>
                        <div>
                            <h4>Philippine National ID</h4>
                            <p>Hold the ID (PhilSys) flat in front of the camera and press Scan, or choose a clear photo of the ID. The face on the ID must match the live face. This ID becomes the only ID accepted when this user signs in with the ID way.</p>
                            <div class="step-actions">
                                <button type="button" class="btn" id="btnIdCamera">Scan ID with camera</button>
                                <input type="file" id="idFile" accept="image/*">
                            </div>
                            <img id="idPreview" class="id-preview" alt="ID photo">
                        </div>
                    </div>

                    <div class="face-step">
                        <span class="step-badge" id="step3">3</span>
                        <div>
                            <h4>Consent and save</h4>
                            <label class="consent-row">
                                <input type="checkbox" id="consent">
                                <span>The user agrees that MediStock saves the numbers of their face to sign in (Data Privacy Act of 2012). The photo of the face and the ID are not saved.</span>
                            </label>
                            <div class="step-actions" style="margin-top:12px;">
                                <a href="{{ route('users') }}" class="btn">Cancel</a>
                                <button type="button" class="btn btn-primary" id="btnSave" disabled>Save face</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
@endsection

@push('scripts')
<script>
    var FACE_PAGE = {
        modelsUrl: "{{ $models_url }}",
        saveUrl: "{{ route('face.enroll.save') }}",
        userId: {{ (int) $user['user_id'] }}
    };
</script>
<script src="{{ asset('js/face-api.min.js') }}"></script>
<script src="{{ asset('js/face-tools.js') }}"></script>
<script src="{{ asset('js/face-enroll.js') }}"></script>
@endpush
