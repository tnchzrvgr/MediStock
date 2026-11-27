<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MediStock - Add New User</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/medistock-icon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/register.css') }}?v={{ filemtime(public_path('css/register.css')) }}">
</head>
<body>
@php
    // the 6 steps from the project guidelines
    $steps = array("Add new user", "Choose kind of ID", "Upload ID for scan", "ID info extraction", "Face recognition and identity matching", "Personal information");
    $active = array_map('intval', explode(',', $__env->yieldContent('active', '0')));
    $first = min($active);
@endphp

<div class="page">

    <div class="brand-panel reg-brand">
        <div class="brand-logo">
            <img src="{{ asset('img/medistock-icon.svg') }}" class="logo-circle" alt="MediStock logo">
            <span class="logo-text">MediStock</span>
        </div>

        <div class="brand-content">
            <h1>Add new user</h1>
            <p class="brand-desc">The ID and the face are checked first. Then the information is filled in automatically.</p>

            <ol class="step-list">
                @foreach ($steps as $i => $text)
                    @php $n = $i + 1; @endphp
                    <li class="{{ in_array($n, $active) ? 'now' : ($n < $first ? 'done' : '') }}">
                        <span class="step-no">{{ $n < $first ? '✓' : $n }}</span>{{ $text }}
                    </li>
                @endforeach
            </ol>
        </div>

        <p class="copyright">&copy; 2026 MediStock. All rights reserved.</p>
    </div>

    <div class="login-panel">
        <div class="reg-box">
            @if (session('reg_notice'))
                <div class="notice warn" style="display:block;">{{ session('reg_notice') }}</div>
            @endif

            @yield('content')
        </div>
    </div>

</div>

@yield('scripts')
</body>
</html>
