<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediStock - {{ $page_title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
</head>
<body>

<div class="layout">

    @include('partials.sidebar')

    <div class="main">

        @include('partials.topbar')

        @include('partials.flash')

        @yield('content')

    </div>
</div>

<script>
// open and close the sidebar on small screens
function toggleMenu() {
    document.getElementById("sidebar").classList.toggle("open");
}
</script>

@stack('scripts')

</body>
</html>
