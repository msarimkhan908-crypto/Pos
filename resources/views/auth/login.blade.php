<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — SahulatPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-theme />
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            background: #f0fdf4;
        }

        /* ── Left branding panel ── */
        .brand-panel {
            display: none;
            width: 50%;
            background: linear-gradient(145deg, #14532d 0%, #166534 30%, #15803d 65%, #16a34a 100%);
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 3rem;
            position: relative;
            overflow: hidden;
        }

        @media (min-width: 1024px) { .brand-panel { display: flex; } }

        .brand-panel::before {
            content: '';
            position: absolute;
            top: -120px; right: -120px;
            width: 380px; height: 380px;
            background: rgba(255,255,255,.06);
            border-radius: 50%;
        }
        .brand-panel::after {
            content: '';
            position: absolute;
            bottom: -160px; left: -100px;
            width: 420px; height: 420px;
            background: rgba(255,255,255,.04);
            border-radius: 50%;
        }

        .brand-inner { position: relative; z-index: 1; text-align: center; color: #fff; max-width: 380px; }

        .brand-icon {
            width: 76px; height: 76px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.75rem;
            backdrop-filter: blur(8px);
        }
        .brand-icon svg { width: 40px; height: 40px; color: #fff; }

        .brand-name {
            font-size: 2.25rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #fff;
            margin-bottom: .25rem;
        }

        .brand-urdu {
            font-family: 'Noto Nastaliq Urdu', serif;
            font-size: 2rem;
            color: rgba(255,255,255,.85);
            line-height: 1.6;
            margin-bottom: .75rem;
            direction: rtl;
        }

        .brand-tagline {
            font-size: .95rem;
            color: rgba(255,255,255,.6);
            letter-spacing: .5px;
            text-transform: uppercase;
            font-weight: 500;
            margin-bottom: 3rem;
        }

        .feature-list { list-style: none; text-align: left; width: 100%; }
        .feature-list li {
            display: flex; align-items: center; gap: .875rem;
            color: rgba(255,255,255,.8);
            font-size: .9rem;
            padding: .6rem 0;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .feature-list li:last-child { border-bottom: none; }
        .feature-list .icon {
            width: 32px; height: 32px; flex-shrink: 0;
            background: rgba(255,255,255,.12);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }
        .feature-list .icon svg { width: 16px; height: 16px; color: #86efac; }

        /* ── Right form panel ── */
        .form-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2.5rem 1.5rem;
            background: #fff;
        }

        .form-box { width: 100%; max-width: 420px; }

        /* Mobile brand header */
        .mobile-brand {
            text-align: center;
            margin-bottom: 2.5rem;
            display: block;
        }
        @media (min-width: 1024px) { .mobile-brand { display: none; } }

        .mobile-brand-name {
            font-size: 1.75rem;
            font-weight: 700;
            color: #15803d;
            letter-spacing: -0.5px;
        }
        .mobile-brand-urdu {
            font-family: 'Noto Nastaliq Urdu', serif;
            font-size: 1.4rem;
            color: #166534;
            direction: rtl;
        }

        /* Form heading */
        .form-heading { margin-bottom: 2rem; }
        .form-heading h1 {
            font-size: 1.6rem;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.4px;
        }
        .form-heading p { font-size: .9rem; color: #6b7280; margin-top: .25rem; }

        /* Input group */
        .field { margin-bottom: 1.25rem; }
        .field label {
            display: block;
            font-size: .8125rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: .4rem;
            letter-spacing: .01em;
        }
        .input-wrap { position: relative; }
        .input-wrap svg {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 16px; height: 16px;
            color: #9ca3af;
            pointer-events: none;
        }
        .field input {
            width: 100%;
            padding: .75rem .875rem .75rem 2.625rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: .9375rem;
            color: #111827;
            background: #f9fafb;
            outline: none;
            transition: border-color .18s, box-shadow .18s, background .18s;
            font-family: 'Inter', sans-serif;
        }
        .field input:focus {
            border-color: #16a34a;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(22,163,74,.12);
        }
        .field input.is-error { border-color: #ef4444; background: #fff; }

        .field-error {
            font-size: .78rem;
            color: #ef4444;
            margin-top: .35rem;
            display: flex; align-items: center; gap: .3rem;
        }

        /* Remember + forgot row */
        .form-extras {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }
        .remember {
            display: flex; align-items: center; gap: .5rem;
            font-size: .85rem; color: #4b5563; cursor: pointer;
        }
        .remember input[type="checkbox"] {
            width: 16px; height: 16px;
            accent-color: #16a34a;
            cursor: pointer;
        }
        .forgot {
            font-size: .85rem;
            color: #16a34a;
            font-weight: 500;
            text-decoration: none;
            transition: color .15s;
        }
        .forgot:hover { color: #15803d; text-decoration: underline; }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: .875rem;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff;
            font-size: .9375rem;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: .01em;
            transition: opacity .18s, transform .12s, box-shadow .18s;
            box-shadow: 0 4px 14px rgba(22,163,74,.35);
            font-family: 'Inter', sans-serif;
        }
        .btn-login:hover {
            opacity: .93;
            box-shadow: 0 6px 20px rgba(22,163,74,.45);
            transform: translateY(-1px);
        }
        .btn-login:active { transform: translateY(0); }

        /* Divider */
        .divider {
            display: flex; align-items: center; gap: .75rem;
            margin: 1.5rem 0;
        }
        .divider::before, .divider::after {
            content: ''; flex: 1; height: 1px; background: #e5e7eb;
        }
        .divider span { font-size: .78rem; color: #9ca3af; white-space: nowrap; }

        /* Register link */
        .register-link {
            text-align: center;
            font-size: .875rem;
            color: #6b7280;
        }
        .register-link a {
            color: #16a34a;
            font-weight: 600;
            text-decoration: none;
        }
        .register-link a:hover { text-decoration: underline; }

        /* Status banner */
        .status-banner {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
            border-radius: 8px;
            padding: .65rem 1rem;
            font-size: .85rem;
            margin-bottom: 1.25rem;
        }

        /* Footer */
        .form-footer {
            margin-top: 2.5rem;
            text-align: center;
            font-size: .78rem;
            color: #d1d5db;
        }
    </style>
</head>
<body>

    {{-- Left branding panel --}}
    <aside class="brand-panel">
        <div class="brand-inner">
            <div class="brand-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z" />
                </svg>
            </div>
            <div class="brand-name">SahulatPOS</div>
            <div class="brand-urdu">سہولت پوائنٹ آف سیل</div>
            <div class="brand-tagline">Smart · Simple · Seamless</div>
            <ul class="feature-list">
                <li>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                    </span>
                    Real-time sales & inventory tracking
                </li>
                <li>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.056 48.056 0 0 1 1.913-.247m10.5 0a48.536 48.536 0 0 0-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" /></svg>
                    </span>
                    Thermal receipt printing
                </li>
                <li>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" /></svg>
                    </span>
                    Multi-role staff management
                </li>
                <li>
                    <span class="icon">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 0 1-1.043 3.296 3.745 3.745 0 0 1-3.296 1.043A3.745 3.745 0 0 1 12 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 0 1-3.296-1.043 3.745 3.745 0 0 1-1.043-3.296A3.745 3.745 0 0 1 3 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 0 1 1.043-3.296 3.746 3.746 0 0 1 3.296-1.043A3.746 3.746 0 0 1 12 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 0 1 3.296 1.043 3.746 3.746 0 0 1 1.043 3.296A3.745 3.745 0 0 1 21 12Z" /></svg>
                    </span>
                    14-day free trial, no card needed
                </li>
            </ul>
        </div>
    </aside>

    {{-- Right form panel --}}
    <main class="form-panel">
        <div class="form-box">

            {{-- Mobile brand (shown only on small screens) --}}
            <div class="mobile-brand">
                <div class="mobile-brand-name">SahulatPOS</div>
                <div class="mobile-brand-urdu">سہولت پوائنٹ آف سیل</div>
            </div>

            {{-- Session status --}}
            @if (session('status'))
                <div class="status-banner">{{ session('status') }}</div>
            @endif

            <div class="form-heading">
                <h1>Welcome back</h1>
                <p>Sign in to your SahulatPOS account</p>
            </div>

            <form method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Email --}}
                <div class="field">
                    <label for="email">Email address</label>
                    <div class="input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                        </svg>
                        <input id="email" type="email" name="email" value="{{ old('email') }}"
                               class="{{ $errors->has('email') ? 'is-error' : '' }}"
                               required autofocus autocomplete="username"
                               placeholder="you@example.com">
                    </div>
                    @error('email')
                        <div class="field-error">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:13px;height:13px"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm0-4a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM7.25 6.75a.75.75 0 0 1 1.5 0v2.5a.75.75 0 0 1-1.5 0v-2.5Z" clip-rule="evenodd" /></svg>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <input id="password" type="password" name="password"
                               class="{{ $errors->has('password') ? 'is-error' : '' }}"
                               required autocomplete="current-password"
                               placeholder="••••••••">
                    </div>
                    @error('password')
                        <div class="field-error">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:13px;height:13px"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm0-4a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM7.25 6.75a.75.75 0 0 1 1.5 0v2.5a.75.75 0 0 1-1.5 0v-2.5Z" clip-rule="evenodd" /></svg>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Remember + Forgot --}}
                <div class="form-extras">
                    <label class="remember">
                        <input type="checkbox" name="remember" id="remember_me">
                        Remember me
                    </label>
                    @if (Route::has('password.request'))
                        <a class="forgot" href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn-login">Sign In</button>

                {{-- Register link --}}
                @if (Route::has('register'))
                    <div class="divider"><span>Don't have an account?</span></div>
                    <div class="register-link">
                        <a href="{{ route('register') }}">Start your free 14-day trial →</a>
                    </div>
                @endif
            </form>

            <div class="form-footer">© {{ date('Y') }} SahulatPOS · سہولت</div>
        </div>
    </main>

</body>
</html>
