<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Start Free Trial — SahulatPOS</title>
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
            width: 46%;
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
            position: absolute; top: -120px; right: -120px;
            width: 380px; height: 380px;
            background: rgba(255,255,255,.06); border-radius: 50%;
        }
        .brand-panel::after {
            content: '';
            position: absolute; bottom: -160px; left: -100px;
            width: 420px; height: 420px;
            background: rgba(255,255,255,.04); border-radius: 50%;
        }

        .brand-inner { position: relative; z-index: 1; text-align: center; color: #fff; max-width: 360px; }

        .brand-icon {
            width: 76px; height: 76px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            backdrop-filter: blur(8px);
        }
        .brand-icon svg { width: 40px; height: 40px; color: #fff; }

        .brand-name { font-size: 2.1rem; font-weight: 700; letter-spacing: -0.5px; margin-bottom: .2rem; }

        .brand-urdu {
            font-family: 'Noto Nastaliq Urdu', serif;
            font-size: 1.9rem;
            color: rgba(255,255,255,.85);
            line-height: 1.6;
            margin-bottom: .6rem;
            direction: rtl;
        }

        .brand-tagline {
            font-size: .875rem; color: rgba(255,255,255,.55);
            letter-spacing: .5px; text-transform: uppercase;
            font-weight: 500; margin-bottom: 2.5rem;
        }

        /* Trial badge */
        .trial-badge {
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 50px;
            padding: .55rem 1.25rem;
            display: inline-flex; align-items: center; gap: .5rem;
            font-size: .85rem; color: #bbf7d0;
            font-weight: 600; margin-bottom: 2rem;
            backdrop-filter: blur(4px);
        }
        .trial-badge svg { width: 15px; height: 15px; }

        .steps { text-align: left; width: 100%; }
        .step {
            display: flex; gap: 1rem; align-items: flex-start;
            padding: .75rem 0;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }
        .step:last-child { border-bottom: none; }
        .step-num {
            width: 28px; height: 28px; flex-shrink: 0;
            background: rgba(255,255,255,.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700; color: #bbf7d0;
        }
        .step-text { color: rgba(255,255,255,.8); font-size: .875rem; line-height: 1.5; }
        .step-text strong { color: #fff; display: block; font-size: .9rem; }

        /* ── Right form panel ── */
        .form-panel {
            flex: 1;
            display: flex; flex-direction: column;
            justify-content: center; align-items: center;
            padding: 2rem 1.5rem;
            background: #fff;
            overflow-y: auto;
        }

        .form-box { width: 100%; max-width: 440px; }

        .mobile-brand { text-align: center; margin-bottom: 2rem; display: block; }
        @media (min-width: 1024px) { .mobile-brand { display: none; } }
        .mobile-brand-name { font-size: 1.6rem; font-weight: 700; color: #15803d; letter-spacing: -0.5px; }
        .mobile-brand-urdu { font-family: 'Noto Nastaliq Urdu', serif; font-size: 1.3rem; color: #166534; direction: rtl; }

        .form-heading { margin-bottom: 1.75rem; }
        .form-heading h1 { font-size: 1.5rem; font-weight: 700; color: #111827; letter-spacing: -0.4px; }
        .form-heading p { font-size: .875rem; color: #6b7280; margin-top: .25rem; }

        /* Two-column grid for name fields */
        .fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
        @media (max-width: 480px) { .fields-grid { grid-template-columns: 1fr; } }

        .field { margin-bottom: 1rem; }
        .field label {
            display: block; font-size: .8125rem; font-weight: 600;
            color: #374151; margin-bottom: .4rem; letter-spacing: .01em;
        }
        .input-wrap { position: relative; }
        .input-wrap svg {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            width: 16px; height: 16px; color: #9ca3af; pointer-events: none;
        }
        .field input {
            width: 100%;
            padding: .7rem .875rem .7rem 2.625rem;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: .9rem; color: #111827;
            background: #f9fafb; outline: none;
            transition: border-color .18s, box-shadow .18s, background .18s;
            font-family: 'Inter', sans-serif;
        }
        .field input:focus {
            border-color: #16a34a; background: #fff;
            box-shadow: 0 0 0 3px rgba(22,163,74,.12);
        }
        .field input.is-error { border-color: #ef4444; background: #fff; }
        .field-error {
            font-size: .78rem; color: #ef4444;
            margin-top: .3rem;
            display: flex; align-items: center; gap: .3rem;
        }

        /* Password strength hint */
        .pw-hint {
            font-size: .75rem; color: #9ca3af; margin-top: .3rem;
        }

        /* Trial note */
        .trial-note {
            display: flex; align-items: center; gap: .5rem;
            background: #f0fdf4; border: 1px solid #bbf7d0;
            border-radius: 8px; padding: .6rem .875rem;
            font-size: .8rem; color: #166534; margin-bottom: 1.25rem;
        }
        .trial-note svg { width: 14px; height: 14px; flex-shrink: 0; color: #16a34a; }

        .btn-register {
            width: 100%;
            padding: .875rem;
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: #fff; font-size: .9375rem; font-weight: 600;
            border: none; border-radius: 10px; cursor: pointer;
            letter-spacing: .01em;
            transition: opacity .18s, transform .12s, box-shadow .18s;
            box-shadow: 0 4px 14px rgba(22,163,74,.35);
            font-family: 'Inter', sans-serif;
        }
        .btn-register:hover {
            opacity: .93;
            box-shadow: 0 6px 20px rgba(22,163,74,.45);
            transform: translateY(-1px);
        }
        .btn-register:active { transform: translateY(0); }

        .login-link {
            text-align: center; margin-top: 1.25rem;
            font-size: .875rem; color: #6b7280;
        }
        .login-link a { color: #16a34a; font-weight: 600; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }

        .form-footer {
            margin-top: 2rem; text-align: center;
            font-size: .78rem; color: #d1d5db;
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

            <div class="trial-badge">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                14-day free trial — no card needed
            </div>

            <div class="steps">
                <div class="step">
                    <div class="step-num">1</div>
                    <div class="step-text">
                        <strong>Create your account</strong>
                        Fill in your business details below
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">2</div>
                    <div class="step-text">
                        <strong>Set up your menu & products</strong>
                        Add categories, items and pricing
                    </div>
                </div>
                <div class="step">
                    <div class="step-num">3</div>
                    <div class="step-text">
                        <strong>Start taking orders</strong>
                        Your POS is live from day one
                    </div>
                </div>
            </div>
        </div>
    </aside>

    {{-- Right form panel --}}
    <main class="form-panel">
        <div class="form-box">

            <div class="mobile-brand">
                <div class="mobile-brand-name">SahulatPOS</div>
                <div class="mobile-brand-urdu">سہولت پوائنٹ آف سیل</div>
            </div>

            <div class="form-heading">
                <h1>Start your free trial</h1>
                <p>Set up your business in under a minute</p>
            </div>

            <form method="POST" action="{{ route('register') }}">
                @csrf

                {{-- Business Name --}}
                <div class="field">
                    <label for="business_name">Business Name</label>
                    <div class="input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 0 1 .75-.75h3a.75.75 0 0 1 .75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349M3.75 21V9.349m0 0a3.001 3.001 0 0 0 3.75-.615A2.993 2.993 0 0 0 9.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 0 0 2.25 1.016c.896 0 1.7-.393 2.25-1.015a3.001 3.001 0 0 0 3.75.614m-16.5 0a3.004 3.004 0 0 1-.621-4.72l1.189-1.19A1.5 1.5 0 0 1 5.378 3h13.243a1.5 1.5 0 0 1 1.06.44l1.19 1.189a3 3 0 0 1-.621 4.72M6.75 18h3.75a.75.75 0 0 0 .75-.75V13.5a.75.75 0 0 0-.75-.75H6.75a.75.75 0 0 0-.75.75v3.75c0 .414.336.75.75.75Z" />
                        </svg>
                        <input id="business_name" type="text" name="business_name"
                               value="{{ old('business_name') }}"
                               class="{{ $errors->has('business_name') ? 'is-error' : '' }}"
                               required autofocus autocomplete="organization"
                               placeholder="e.g. Saeed General Store">
                    </div>
                    @error('business_name')
                        <div class="field-error">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:13px;height:13px"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm0-4a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM7.25 6.75a.75.75 0 0 1 1.5 0v2.5a.75.75 0 0 1-1.5 0v-2.5Z" clip-rule="evenodd" /></svg>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Business Type --}}
                <div class="field">
                    <label for="business_type">Business Type</label>
                    <div class="input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                        <select id="business_type" name="business_type"
                                class="{{ $errors->has('business_type') ? 'is-error' : '' }}"
                                required style="appearance:auto;background:transparent;cursor:pointer;">
                            <option value="" disabled {{ old('business_type') ? '' : 'selected' }}>Select your business type…</option>
                            @foreach (\App\Models\Organization::BUSINESS_TYPES as $value => $label)
                                <option value="{{ $value }}" {{ old('business_type') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    @error('business_type')
                        <div class="field-error">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:13px;height:13px"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm0-4a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM7.25 6.75a.75.75 0 0 1 1.5 0v2.5a.75.75 0 0 1-1.5 0v-2.5Z" clip-rule="evenodd" /></svg>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Name + Email side by side --}}
                <div class="fields-grid">
                    <div class="field" style="margin-bottom:0">
                        <label for="name">Your Name</label>
                        <div class="input-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                            <input id="name" type="text" name="name"
                                   value="{{ old('name') }}"
                                   class="{{ $errors->has('name') ? 'is-error' : '' }}"
                                   required autocomplete="name"
                                   placeholder="Full name">
                        </div>
                        @error('name')
                            <div class="field-error">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:13px;height:13px"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm0-4a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM7.25 6.75a.75.75 0 0 1 1.5 0v2.5a.75.75 0 0 1-1.5 0v-2.5Z" clip-rule="evenodd" /></svg>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="field" style="margin-bottom:0">
                        <label for="email">Email Address</label>
                        <div class="input-wrap">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                            </svg>
                            <input id="email" type="email" name="email"
                                   value="{{ old('email') }}"
                                   class="{{ $errors->has('email') ? 'is-error' : '' }}"
                                   required autocomplete="username"
                                   placeholder="you@example.com">
                        </div>
                        @error('email')
                            <div class="field-error">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:13px;height:13px"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm0-4a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM7.25 6.75a.75.75 0 0 1 1.5 0v2.5a.75.75 0 0 1-1.5 0v-2.5Z" clip-rule="evenodd" /></svg>
                                {{ $message }}
                            </div>
                        @enderror
                    </div>
                </div>

                {{-- Password --}}
                <div class="field" style="margin-top:1rem">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <input id="password" type="password" name="password"
                               class="{{ $errors->has('password') ? 'is-error' : '' }}"
                               required autocomplete="new-password"
                               placeholder="Min 8 characters">
                    </div>
                    @error('password')
                        <div class="field-error">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:13px;height:13px"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm0-4a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM7.25 6.75a.75.75 0 0 1 1.5 0v2.5a.75.75 0 0 1-1.5 0v-2.5Z" clip-rule="evenodd" /></svg>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Confirm Password --}}
                <div class="field">
                    <label for="password_confirmation">Confirm Password</label>
                    <div class="input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                        <input id="password_confirmation" type="password" name="password_confirmation"
                               class="{{ $errors->has('password_confirmation') ? 'is-error' : '' }}"
                               required autocomplete="new-password"
                               placeholder="Repeat password">
                    </div>
                    @error('password_confirmation')
                        <div class="field-error">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:13px;height:13px"><path fill-rule="evenodd" d="M8 15A7 7 0 1 0 8 1a7 7 0 0 0 0 14Zm0-4a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM7.25 6.75a.75.75 0 0 1 1.5 0v2.5a.75.75 0 0 1-1.5 0v-2.5Z" clip-rule="evenodd" /></svg>
                            {{ $message }}
                        </div>
                    @enderror
                </div>

                {{-- Trial note --}}
                <div class="trial-note">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                    14-day free trial · No credit card required · Cancel anytime
                </div>

                {{-- Submit --}}
                <button type="submit" class="btn-register">Create My Account →</button>

                <div class="login-link">
                    Already have an account? <a href="{{ route('login') }}">Sign in</a>
                </div>
            </form>

            <div class="form-footer">© {{ date('Y') }} SahulatPOS · سہولت</div>
        </div>
    </main>

</body>
</html>
