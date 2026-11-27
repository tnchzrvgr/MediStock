@extends('register.layout')

@section('active', '3,4')

@section('content')
<div class="form-heading">
    <h2>Upload ID for scan</h2>
    <p>Kind of ID: <strong>{{ $type_name }}</strong> (<a href="{{ route('register') }}">change</a>). Upload a clear photo of the front side. We read it for you.</p>
</div>

<input type="hidden" id="idType" value="{{ $type }}">

<div class="field">
    <label for="idFile">Photo of the ID</label>
    <input type="file" id="idFile" accept="image/*">
    <p class="small-note">Tips: flat on a table, bright light, no glare, all four corners visible, not blurry.</p>
</div>

<img id="idPreview" class="id-preview" alt="Preview of your ID" style="display:none;">

<div id="scanStatus" class="notice info" style="display:none;"></div>

<div id="scanResult" style="display:none;">
    <h3 class="result-title">Information read from the ID <span class="small-note">(reading confidence <span id="ocrConf">0%</span>)</span></h3>
    <table class="result-table">
        <thead><tr><th>Field</th><th>Value (you can correct it)</th><th>Status</th></tr></thead>
        <tbody id="resultBody"></tbody>
    </table>
    <p class="small-note">If something was read wrongly or not read, type it here. Next steps: face recognition, then the personal information page.</p>
</div>

<button type="button" id="btnConfirm" class="btn-signin" style="display:none; margin-bottom:10px;">Confirm and continue to face scan</button>
<button type="button" id="btnScan" class="btn-signin" disabled>Scan ID</button>
<button type="button" id="btnRetry" class="btn-alt" style="display:none; margin-top:10px;">Upload the ID again</button>

<p class="back-link"><a href="{{ route('register.reset') }}">Cancel and go back</a></p>
@endsection

@section('scripts')
<script>
    var REG = {
        modelsUrl: "{{ $models_url }}",
        idUrl: "{{ route('register.id.save') }}"
    };
</script>
<script src="{{ asset('js/face-api.min.js') }}"></script>
<!-- OCR (Tesseract.js, open source). To use it offline, save the files in public/js/tesseract/ - see public/js/README-registration.txt -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.1.1/dist/tesseract.min.js"></script>
<script src="{{ asset('js/register-parse.js') }}"></script>
<script src="{{ asset('js/register-id.js') }}"></script>
@endsection
