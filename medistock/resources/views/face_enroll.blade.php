@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/face.css') }}">

<style>
    .face-layout { display: flex; gap: 20px; align-items: flex-start; }
    .face-left { flex: 1; min-width: 0; }
    .face-right { width: 380px; flex-shrink: 0; }
    .face-left .cam-box { max-width: 480px; margin: 14px auto 0 auto; }
    .face-note { text-align: center; font-size: 12px; color: #64748b; margin: 8px 0 0 0; }
    .face-step { display: flex; gap: 12px; padding: 14px 0; border-bottom: 1px solid #e2e8f0; }
    .face-step:last-of-type { border-bottom: none; }
    .face-step .step-text { flex: 1; }
    .face-step h4 { margin: 0 0 4px 0; font-size: 14px; color: #1e293b; }
    .face-step p { margin: 0 0 10px 0; font-size: 12px; color: #475569; line-height: 1.45; }
    .face-consent { display: flex; gap: 8px; align-items: flex-start; font-size: 12px; color: #475569; margin-bottom: 12px; cursor: pointer; }
    .face-consent input { margin-top: 2px; }
    .face-buttons { display: flex; gap: 10px; align-items: center; }
    
    @media (max-width: 900px) {
        .face-layout { flex-direction: column; }
        .face-right { width: 100%; }
    }
</style>

<meta name="csrf-token" content="{{ csrf_token() }}">
<form onsubmit="return false;" style="display:none;">@csrf</form>   <!-- the token that face-tools.js sends with the save -->

<div class="face-layout">

    <div class="card face-left">
        <div class="card-title">Camera</div>

        <div class="cam-box">
            <video id="cam" muted playsinline></video>
            <div class="cam-oval"></div>
        </div>

        <p class="cam-message" id="camMessage">Press "Start camera".</p>
        <p class="face-note">The photos are only used to read numbers. They are never saved.</p>
    </div>

    <div class="card face-right">
        <div class="card-title">Register face of {{ $user['full_name'] }}</div>
        <div class="card-sub">{{ $has_face ? 'A face is already saved. Registering again replaces it.' : 'After this, the user signs in with the password and the face check.' }}</div>

        <div class="face-step">
            <span class="step-badge" id="step1">1</span>
            <div class="step-text">
                <h4>Live face</h4>
                <p>The user sits in front of the camera, looks straight, then slowly turns the head to the left and to the right when asked.</p>
                <button type="button" id="btnLive" class="btn btn-primary">Start camera</button>
            </div>
        </div>

        <div class="face-step">
            <span class="step-badge" id="step2">2</span>
            <div class="step-text">
                <h4>Consent and save</h4>
                <label class="face-consent">
                    <input type="checkbox" id="consent">
                    <span>The user agrees that MediStock saves the numbers of their face to sign in (Data Privacy Act of 2012). The photo of the face is not saved.</span>
                </label>
                <div class="face-buttons">
                    <a href="{{ route('users') }}" class="btn">Cancel</a>
                    <button type="button" id="btnSave" class="btn btn-primary" disabled>Save face</button>
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
