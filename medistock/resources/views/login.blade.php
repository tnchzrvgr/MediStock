<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediStock - Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
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
                <h1>Smarter pharmacy inventory and sales analysis.</h1>
                <p class="brand-desc">Monitor medicine demand, stock levels and sales trends in one integrated system.</p>

                <ul class="feature-list">
                    <li><span class="dot"></span>Real-time stock &amp; expiry alerts</li>
                    <li><span class="dot"></span>Demand forecasting and sales analytics</li>
                    <li><span class="dot"></span>Role-based access for every staff member</li>
                </ul>
            </div>

            <p class="copyright">&copy; 2026 MediStock. All rights reserved.</p>
        </div>

        <!-- right side (login form) -->
        <div class="login-panel">
            <div class="login-form">
                <div class="form-heading">
                    <h2>Welcome back</h2>
                    <p>Sign in to access your pharmacy workspace</p>
                </div>

                @if ($error != "")
                    <div class="error">{{ $error }}</div>
                @endif

                <form method="POST" action="{{ route('login.check') }}">
                    @csrf
                    <div class="field">
                        <label for="username">Email / Username</label>
                        <input type="text" name="username" id="username" placeholder="you@medistock.ph" value="{{ $saved_username }}">
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" placeholder="••••••••••">
                    </div>

                    <div class="options">
                        <label class="remember">
                            <input type="checkbox" name="remember">
                            Remember me
                        </label>
                    </div>

                    <button type="submit" name="login" class="btn-signin">Sign In</button>
                </form>

                <div class="role-note">
                    <strong>Role-based access</strong>
                    <p>You'll only see the modules your role allows: Owner/Admin or Pharmacy Staff.</p>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
