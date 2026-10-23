<!DOCTYPE html>
<html lang="ur" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SahulatPOS — سہولت پوائنٹ آف سیل</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Noto+Nastaliq+Urdu:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green-50: #f0fdf4;
            --green-100: #dcfce7;
            --green-200: #bbf7d0;
            --green-400: #4ade80;
            --green-500: #22c55e;
            --green-600: #16a34a;
            --green-700: #15803d;
            --green-800: #166534;
            --green-900: #14532d;
            --dark: #0f172a;
            --dark-2: #1e293b;
            --text-muted: #64748b;
            --text-light: #94a3b8;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #f1f5f9;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* ─── NAVBAR ─── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            padding: 0 2rem;
            height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            transition: all 0.3s;
        }

        nav.scrolled {
            background: rgba(15, 23, 42, 0.97);
            box-shadow: 0 4px 30px rgba(0,0,0,0.4);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            text-decoration: none;
        }

        .nav-logo-icon {
            width: 36px; height: 36px;
            background: linear-gradient(135deg, var(--green-500), var(--green-700));
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
        }

        .nav-logo-text {
            display: flex; flex-direction: column;
            line-height: 1.1;
        }

        .nav-logo-en {
            font-size: 1rem;
            font-weight: 700;
            color: #f1f5f9;
            letter-spacing: -0.02em;
        }

        .nav-logo-ur {
            font-family: 'Noto Nastaliq Urdu', serif;
            font-size: 0.65rem;
            color: var(--green-400);
            direction: rtl;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link {
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .nav-link:hover { color: #f1f5f9; background: rgba(255,255,255,0.07); }

        .nav-cta {
            background: var(--green-600);
            color: #fff !important;
            padding: 0.45rem 1.2rem !important;
            border-radius: 8px;
            font-weight: 600;
        }

        .nav-cta:hover { background: var(--green-500) !important; }

        /* ─── HERO ─── */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            padding: 6rem 1.5rem 4rem;
        }

        /* Animated gradient background */
        .hero-bg {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(34,197,94,0.18) 0%, transparent 70%),
                        radial-gradient(ellipse 50% 40% at 80% 80%, rgba(22,163,74,0.10) 0%, transparent 60%),
                        #0f172a;
        }

        /* Floating orbs */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.25;
            animation: float 8s ease-in-out infinite;
        }

        .orb-1 {
            width: 500px; height: 500px;
            background: radial-gradient(circle, var(--green-500), transparent);
            top: -150px; left: -100px;
            animation-delay: 0s;
        }

        .orb-2 {
            width: 400px; height: 400px;
            background: radial-gradient(circle, var(--green-700), transparent);
            bottom: -100px; right: -80px;
            animation-delay: -3s;
        }

        .orb-3 {
            width: 300px; height: 300px;
            background: radial-gradient(circle, #0ea5e9, transparent);
            top: 40%; left: 60%;
            opacity: 0.1;
            animation-delay: -5s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.97); }
        }

        /* Grid overlay */
        .hero-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.025) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.025) 1px, transparent 1px);
            background-size: 60px 60px;
            mask-image: radial-gradient(ellipse 70% 70% at 50% 50%, black 0%, transparent 100%);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 820px;
            width: 100%;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(34,197,94,0.12);
            border: 1px solid rgba(34,197,94,0.3);
            color: var(--green-400);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.35rem 1rem;
            border-radius: 100px;
            margin-bottom: 1.8rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            animation: fadeDown 0.7s ease both;
        }

        .hero-badge span { animation: pulse-dot 2s ease infinite; }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        .hero-urdu {
            font-family: 'Noto Nastaliq Urdu', serif;
            font-size: clamp(1.5rem, 4vw, 2.5rem);
            color: var(--green-400);
            direction: rtl;
            margin-bottom: 0.6rem;
            display: block;
            line-height: 1.8;
            animation: fadeDown 0.8s ease 0.1s both;
        }

        .hero-title {
            font-size: clamp(2.6rem, 7vw, 5rem);
            font-weight: 900;
            line-height: 1.05;
            letter-spacing: -0.04em;
            margin-bottom: 1rem;
            animation: fadeDown 0.8s ease 0.15s both;
        }

        .hero-title .highlight {
            background: linear-gradient(135deg, var(--green-400) 0%, var(--green-500) 50%, #34d399 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Typing effect container */
        .typing-line {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: clamp(1rem, 2.5vw, 1.4rem);
            color: #94a3b8;
            font-weight: 400;
            margin-bottom: 2.5rem;
            animation: fadeDown 0.8s ease 0.2s both;
            min-height: 2rem;
        }

        .typing-word {
            color: var(--green-400);
            font-weight: 600;
            border-right: 2px solid var(--green-400);
            padding-right: 3px;
            white-space: nowrap;
            overflow: hidden;
            animation: typing 0.4s steps(20) forwards, blink 0.7s step-end infinite;
        }

        @keyframes blink {
            0%, 100% { border-color: var(--green-400); }
            50% { border-color: transparent; }
        }

        .hero-desc {
            font-size: 1.1rem;
            color: #94a3b8;
            max-width: 560px;
            margin: 0 auto 2.5rem;
            line-height: 1.8;
            animation: fadeDown 0.8s ease 0.25s both;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeDown 0.8s ease 0.3s both;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-700));
            color: #fff;
            text-decoration: none;
            padding: 0.9rem 2.2rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.25s;
            box-shadow: 0 4px 24px rgba(34,197,94,0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-primary::after {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(255,255,255,0);
            transition: background 0.2s;
        }

        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 32px rgba(34,197,94,0.4); }
        .btn-primary:hover::after { background: rgba(255,255,255,0.08); }

        .btn-secondary {
            background: rgba(255,255,255,0.06);
            color: #f1f5f9;
            text-decoration: none;
            padding: 0.9rem 2.2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            border: 1px solid rgba(255,255,255,0.12);
            transition: all 0.25s;
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.1);
            transform: translateY(-2px);
        }

        .hero-stats {
            display: flex;
            gap: 2.5rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 3.5rem;
            animation: fadeDown 0.8s ease 0.4s both;
        }

        .hero-stat {
            text-align: center;
        }

        .hero-stat-num {
            display: block;
            font-size: 1.8rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--green-400), var(--green-500));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-stat-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        /* ─── SCROLL INDICATOR ─── */
        .scroll-indicator {
            position: absolute;
            bottom: 2rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.3rem;
            color: #475569;
            font-size: 0.75rem;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            animation: bounce-down 2s ease infinite;
        }

        .scroll-mouse {
            width: 22px; height: 34px;
            border: 2px solid #334155;
            border-radius: 12px;
            position: relative;
        }

        .scroll-mouse::after {
            content: '';
            position: absolute;
            width: 4px; height: 6px;
            background: var(--green-500);
            border-radius: 2px;
            top: 4px; left: 50%;
            transform: translateX(-50%);
            animation: scroll-wheel 1.5s ease infinite;
        }

        @keyframes scroll-wheel {
            0% { opacity: 1; transform: translateX(-50%) translateY(0); }
            100% { opacity: 0; transform: translateX(-50%) translateY(12px); }
        }

        @keyframes bounce-down {
            0%, 100% { transform: translateX(-50%) translateY(0); }
            50% { transform: translateX(-50%) translateY(6px); }
        }

        /* ─── SECTION STYLES ─── */
        section { padding: 6rem 1.5rem; }

        .section-inner { max-width: 1100px; margin: 0 auto; }

        .section-tag {
            display: inline-block;
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.25);
            color: var(--green-400);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.3rem 0.9rem;
            border-radius: 100px;
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
        }

        .section-title .green { color: var(--green-400); }

        .section-sub {
            font-size: 1.05rem;
            color: #94a3b8;
            max-width: 520px;
            line-height: 1.7;
        }

        /* ─── FEATURES ─── */
        .features { background: #0f172a; }

        .features-header { text-align: center; margin-bottom: 3.5rem; }
        .features-header .section-sub { margin: 0 auto; }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .feature-card {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(34,197,94,0.4), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-4px);
            border-color: rgba(34,197,94,0.2);
            background: rgba(30, 41, 59, 0.9);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .feature-card:hover::before { opacity: 1; }

        .feature-icon {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, rgba(34,197,94,0.2), rgba(22,163,74,0.1));
            border: 1px solid rgba(34,197,94,0.25);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.2rem;
        }

        .feature-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #f1f5f9;
        }

        .feature-desc {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.7;
        }

        /* ─── HOW IT WORKS ─── */
        .how-it-works {
            background: linear-gradient(180deg, #0f172a 0%, #0d1520 100%);
        }

        .steps-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
            position: relative;
        }

        .step {
            text-align: center;
            position: relative;
        }

        .step-num {
            width: 64px; height: 64px;
            background: linear-gradient(135deg, var(--green-600), var(--green-800));
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.4rem;
            font-weight: 900;
            margin: 0 auto 1.2rem;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 0 6px rgba(34,197,94,0.1);
        }

        .step-title {
            font-weight: 700;
            font-size: 1.05rem;
            margin-bottom: 0.5rem;
        }

        .step-desc {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
        }

        /* ─── PRICING ─── */
        .pricing { background: #0f172a; }
        .pricing-header { text-align: center; margin-bottom: 3rem; }

        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.5rem;
            max-width: 1100px;
            margin: 0 auto;
        }

        .pricing-card {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255,255,255,0.07);
            border-radius: 24px;
            padding: 2.5rem 2rem;
            transition: all 0.3s;
        }

        .pricing-card.featured {
            background: linear-gradient(145deg, rgba(22,163,74,0.15), rgba(15,111,48,0.08));
            border-color: rgba(34,197,94,0.35);
            position: relative;
            overflow: hidden;
        }

        .pricing-card.featured::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(34,197,94,0.05) 0%, transparent 60%);
        }

        .pricing-popular {
            display: inline-block;
            background: linear-gradient(135deg, var(--green-500), var(--green-600));
            color: #fff;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.2rem 0.7rem;
            border-radius: 100px;
            margin-bottom: 1rem;
        }

        .pricing-name {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .pricing-price {
            font-size: 2.5rem;
            font-weight: 900;
            letter-spacing: -0.04em;
            line-height: 1;
            margin: 1rem 0 0.3rem;
        }

        .pricing-price sub {
            font-size: 1rem;
            font-weight: 500;
            color: #64748b;
        }

        .pricing-period {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        .pricing-features {
            list-style: none;
            margin-bottom: 2rem;
        }

        .pricing-features li {
            font-size: 0.9rem;
            color: #94a3b8;
            padding: 0.4rem 0;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .pricing-features li::before {
            content: '✓';
            color: var(--green-400);
            font-weight: 700;
            flex-shrink: 0;
        }

        .btn-plan {
            display: block;
            text-align: center;
            text-decoration: none;
            padding: 0.8rem 1.5rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: all 0.25s;
        }

        .btn-plan-outline {
            border: 1.5px solid rgba(255,255,255,0.15);
            color: #f1f5f9;
        }

        .btn-plan-outline:hover { background: rgba(255,255,255,0.07); }

        .btn-plan-green {
            background: linear-gradient(135deg, var(--green-500), var(--green-700));
            color: #fff;
            box-shadow: 0 4px 20px rgba(34,197,94,0.25);
        }

        .btn-plan-green:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(34,197,94,0.35); }

        /* ─── CTA SECTION ─── */
        .cta-section {
            background: linear-gradient(135deg, rgba(22,163,74,0.15) 0%, rgba(15,23,42,0) 100%);
            border-top: 1px solid rgba(34,197,94,0.12);
            border-bottom: 1px solid rgba(34,197,94,0.12);
        }

        .cta-inner {
            max-width: 700px;
            margin: 0 auto;
            text-align: center;
        }

        .cta-urdu {
            font-family: 'Noto Nastaliq Urdu', serif;
            font-size: 1.6rem;
            color: var(--green-400);
            direction: rtl;
            display: block;
            margin-bottom: 0.5rem;
            line-height: 2;
        }

        .cta-title {
            font-size: clamp(1.8rem, 4vw, 2.5rem);
            font-weight: 800;
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
        }

        .cta-sub {
            font-size: 1rem;
            color: #94a3b8;
            margin-bottom: 2rem;
        }

        .cta-note {
            font-size: 0.8rem;
            color: #475569;
            margin-top: 1rem;
        }

        /* ─── FOOTER ─── */
        footer {
            background: #080e1a;
            padding: 3rem 1.5rem 2rem;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .footer-inner {
            max-width: 1100px;
            margin: 0 auto;
        }

        .footer-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            margin-bottom: 1.5rem;
        }

        .footer-logo-en { font-size: 1.1rem; font-weight: 800; color: #f1f5f9; }
        .footer-logo-ur {
            font-family: 'Noto Nastaliq Urdu', serif;
            font-size: 0.8rem;
            color: var(--green-400);
            direction: rtl;
        }

        .footer-links {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: #475569;
            text-decoration: none;
            font-size: 0.875rem;
            transition: color 0.2s;
        }

        .footer-links a:hover { color: var(--green-400); }

        .footer-copy {
            font-size: 0.8rem;
            color: #334155;
            text-align: center;
        }

        /* ─── ANIMATIONS ─── */
        @keyframes fadeDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 640px) {
            nav { padding: 0 1rem; }
            .nav-links .nav-link:not(.nav-cta) { display: none; }
            .hero-stats { gap: 1.5rem; }
            .footer-top { flex-direction: column; align-items: flex-start; }
        }

        /* marquee ticker */
        .ticker-wrap {
            background: rgba(34,197,94,0.08);
            border-top: 1px solid rgba(34,197,94,0.15);
            border-bottom: 1px solid rgba(34,197,94,0.15);
            padding: 0.6rem 0;
            overflow: hidden;
            white-space: nowrap;
        }

        .ticker-track {
            display: inline-block;
            animation: ticker 30s linear infinite;
        }

        .ticker-track span {
            font-size: 0.8rem;
            color: var(--green-400);
            letter-spacing: 0.05em;
            font-weight: 500;
            padding: 0 2.5rem;
        }

        .ticker-track span.sep {
            color: rgba(34,197,94,0.3);
            padding: 0;
        }

        @keyframes ticker {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav id="navbar">
    <a href="/" class="nav-logo">
        <div class="nav-logo-icon">🛒</div>
        <div class="nav-logo-text">
            <span class="nav-logo-en">SahulatPOS</span>
            <span class="nav-logo-ur">سہولت</span>
        </div>
    </a>
    <div class="nav-links">
        <a href="#features" class="nav-link">Features</a>
        <a href="#how-it-works" class="nav-link">How It Works</a>
        <a href="#pricing" class="nav-link">Pricing</a>
        @if (Route::has('login'))
            @auth
                <a href="{{ url('/dashboard') }}" class="nav-link nav-cta">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="nav-link">Log in</a>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="nav-link nav-cta">Get Started Free</a>
                @endif
            @endauth
        @endif
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="hero-grid"></div>

    <div class="hero-content">
        <div class="hero-badge">
            <span>●</span> Now with Cloud Sync &amp; Mobile Support
        </div>

        <span class="hero-urdu">سہولت پوائنٹ آف سیل</span>

        <h1 class="hero-title">
            The Smarter Way to<br>
            Run Your <span class="highlight" id="heroWord">Business</span>
        </h1>

        <div class="typing-line">
            Fast &amp; simple POS for&nbsp;
            <span id="typingTarget" class="typing-word">Retail Shops</span>
        </div>

        <p class="hero-desc">
            Complete point-of-sale system with inventory tracking, sales reports, multi-user roles, and cloud sync — all in one beautiful app.
        </p>

        <div class="hero-actions">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn-primary">
                    Start Free 14-Day Trial →
                </a>
            @endif
            <a href="#features" class="btn-secondary">See Features</a>
        </div>

        <div class="hero-stats">
            <div class="hero-stat">
                <span class="hero-stat-num" data-count="500">0</span>
                <span class="hero-stat-label">Businesses</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-num" data-count="50000">0</span>
                <span class="hero-stat-label">Transactions</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-num" data-count="99">0</span>
                <span class="hero-stat-label">% Uptime</span>
            </div>
            <div class="hero-stat">
                <span class="hero-stat-num" data-count="14">0</span>
                <span class="hero-stat-label">Day Free Trial</span>
            </div>
        </div>
    </div>

    <div class="scroll-indicator">
        <div class="scroll-mouse"></div>
        <span>Scroll</span>
    </div>
</section>

<!-- TICKER -->
<div class="ticker-wrap">
    <div class="ticker-track">
        <span>🛒 Sales Management</span><span class="sep">•</span>
        <span>📦 Inventory Tracking</span><span class="sep">•</span>
        <span>📊 Real-time Reports</span><span class="sep">•</span>
        <span>👥 Multi-user Roles</span><span class="sep">•</span>
        <span>☁️ Cloud Sync</span><span class="sep">•</span>
        <span>🖥️ Desktop App</span><span class="sep">•</span>
        <span>📱 Mobile Friendly</span><span class="sep">•</span>
        <span>🔒 Secure & Private</span><span class="sep">•</span>
        <span>🇵🇰 Made for Pakistan</span><span class="sep">•</span>
        <!-- duplicate for seamless loop -->
        <span>🛒 Sales Management</span><span class="sep">•</span>
        <span>📦 Inventory Tracking</span><span class="sep">•</span>
        <span>📊 Real-time Reports</span><span class="sep">•</span>
        <span>👥 Multi-user Roles</span><span class="sep">•</span>
        <span>☁️ Cloud Sync</span><span class="sep">•</span>
        <span>🖥️ Desktop App</span><span class="sep">•</span>
        <span>📱 Mobile Friendly</span><span class="sep">•</span>
        <span>🔒 Secure & Private</span><span class="sep">•</span>
        <span>🇵🇰 Made for Pakistan</span><span class="sep">•</span>
    </div>
</div>

<!-- FEATURES -->
<section class="features" id="features">
    <div class="section-inner">
        <div class="features-header fade-in">
            <div class="section-tag">Features</div>
            <h2 class="section-title">Everything you need to <span class="green">sell smarter</span></h2>
            <p class="section-sub">Powerful tools designed for Pakistani businesses — simple enough for anyone to use.</p>
        </div>

        <div class="features-grid">
            <div class="feature-card fade-in">
                <div class="feature-icon">🛒</div>
                <h3 class="feature-title">Fast Checkout</h3>
                <p class="feature-desc">Process sales in seconds with barcode scanning, quick item search, and one-tap checkout. No slowdowns at peak hours.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">📊</div>
                <h3 class="feature-title">Real-time Reports</h3>
                <p class="feature-desc">Daily, weekly and monthly sales reports. Know your best sellers, busiest hours, and profit margins at a glance.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">👥</div>
                <h3 class="feature-title">Multi-user Roles</h3>
                <p class="feature-desc">Admin, Manager, and Cashier roles. Control who sees what — protect your data while empowering your team.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">☁️</div>
                <h3 class="feature-title">Cloud Sync</h3>
                <p class="feature-desc">Your data is always backed up and synced. Access your business reports from any device, anywhere, anytime.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">🖥️</div>
                <h3 class="feature-title">Works Offline</h3>
                <p class="feature-desc">Internet down? No problem. The desktop app keeps working offline and syncs everything back up when you reconnect.</p>
            </div>
            <div class="feature-card fade-in">
                <div class="feature-icon">🖨️</div>
                <h3 class="feature-title">Receipt Printing</h3>
                <p class="feature-desc">Print professional receipts on thermal printers. Customers get clean, branded receipts every time.</p>
            </div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-it-works" id="how-it-works">
    <div class="section-inner">
        <div class="features-header fade-in">
            <div class="section-tag">How It Works</div>
            <h2 class="section-title">Up and running in <span class="green">3 simple steps</span></h2>
            <p class="section-sub">No technical knowledge needed. Start selling within minutes of signing up.</p>
        </div>

        <div class="steps-row">
            <div class="step fade-in">
                <div class="step-num">1</div>
                <h3 class="step-title">Create Your Account</h3>
                <p class="step-desc">Sign up free in under a minute. Enter your business name and you're ready to go.</p>
            </div>
            <div class="step fade-in">
                <div class="step-num">2</div>
                <h3 class="step-title">Add Your Products</h3>
                <p class="step-desc">Add products with prices, categories, and barcodes. Import from Excel if you have a list ready.</p>
            </div>
            <div class="step fade-in">
                <div class="step-num">3</div>
                <h3 class="step-title">Start Selling</h3>
                <p class="step-desc">Open the register and start processing sales. It's that easy — no training required.</p>
            </div>
        </div>
    </div>
</section>

<!-- PRICING -->
<section class="pricing" id="pricing">
    <div class="section-inner">
        <div class="pricing-header fade-in">
            <div class="section-tag">Pricing</div>
            <h2 class="section-title">Simple, <span class="green">fair pricing</span></h2>
            <p class="section-sub" style="margin:0 auto;">Start free. Upgrade when you're ready. No hidden fees.</p>
        </div>

        <div class="pricing-grid fade-in" style="max-width:1100px;">
            <div class="pricing-card">
                <div class="pricing-name">Free Trial</div>
                <div class="pricing-price" style="color:#f1f5f9;">Free</div>
                <div class="pricing-period">14 days · no credit card needed</div>
                <ul class="pricing-features">
                    <li>Unlimited sales</li>
                    <li>Up to 100 products</li>
                    <li>1 user account</li>
                    <li>Basic reports</li>
                    <li>Receipt printing</li>
                </ul>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn-plan btn-plan-outline">Start Free Trial</a>
                @endif
            </div>

            <div class="pricing-card featured">
                <div class="pricing-popular">Most Popular</div>
                <div class="pricing-name">Monthly</div>
                <div class="pricing-price" style="background:linear-gradient(135deg,#4ade80,#22c55e);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">
                    PKR 2,499
                </div>
                <div class="pricing-period">per month</div>
                <ul class="pricing-features">
                    <li>Unlimited sales & products</li>
                    <li>Up to 5 user accounts</li>
                    <li>Advanced reports & analytics</li>
                    <li>Cloud sync & backup</li>
                    <li>Desktop + Web app</li>
                    <li>Priority support</li>
                </ul>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn-plan btn-plan-green">Get Started</a>
                @endif
            </div>

            <div class="pricing-card" style="border-color:rgba(14,165,233,0.25);">
                <div class="pricing-popular" style="background:linear-gradient(135deg,#0ea5e9,#0284c7);">Best Value</div>
                <div class="pricing-name">Annual</div>
                <div class="pricing-price" style="color:#f1f5f9;">PKR 14,999</div>
                <div class="pricing-period">per year · <span style="color:#4ade80;font-weight:600;">save PKR 14,989</span></div>
                <ul class="pricing-features">
                    <li>Everything in Monthly</li>
                    <li>2 months free</li>
                    <li>Unlimited user accounts</li>
                    <li>Priority onboarding</li>
                    <li>Dedicated support</li>
                    <li>Early access to new features</li>
                </ul>
                @if (Route::has('register'))
                    <a href="{{ route('register') }}" class="btn-plan btn-plan-outline" style="border-color:rgba(14,165,233,0.4);color:#7dd3fc;">Get Annual Plan</a>
                @endif
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="cta-inner fade-in">
        <span class="cta-urdu">آج ہی شروع کریں</span>
        <h2 class="cta-title">Ready to grow your business?</h2>
        <p class="cta-sub">Join hundreds of Pakistani businesses already using SahulatPOS. Start your free 14-day trial — no credit card required.</p>
        <div class="hero-actions">
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="btn-primary">Create Free Account →</a>
            @endif
            @if (Route::has('login'))
                <a href="{{ route('login') }}" class="btn-secondary">Sign In</a>
            @endif
        </div>
        <p class="cta-note">✓ 14-day free trial &nbsp;·&nbsp; ✓ No credit card &nbsp;·&nbsp; ✓ Cancel anytime</p>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-top">
            <div>
                <div class="footer-logo-en">SahulatPOS</div>
                <div class="footer-logo-ur">سہولت پوائنٹ آف سیل</div>
            </div>
            <div class="footer-links">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#pricing">Pricing</a>
                @if (Route::has('login'))
                    <a href="{{ route('login') }}">Login</a>
                @endif
                @if (Route::has('register'))
                    <a href="{{ route('register') }}">Register</a>
                @endif
            </div>
        </div>
        <p class="footer-copy">© {{ date('Y') }} SahulatPOS — سہولت پوائنٹ آف سیل. All rights reserved.</p>
    </div>
</footer>

<script>
    // Navbar scroll effect
    const navbar = document.getElementById('navbar');
    window.addEventListener('scroll', () => {
        navbar.classList.toggle('scrolled', window.scrollY > 40);
    });

    // Typing effect — cycles through business types
    const words = ['Retail Shops', 'Grocery Stores', 'Pharmacies', 'Clothing Stores', 'Restaurants', 'Electronics Shops'];
    const heroWords = ['Business', 'Retail Shop', 'Grocery Store', 'Pharmacy', 'Restaurant', 'Store'];
    let wordIndex = 0;
    const typingEl = document.getElementById('typingTarget');
    const heroWordEl = document.getElementById('heroWord');

    function typeWord(el, text, cb) {
        el.textContent = '';
        let i = 0;
        const timer = setInterval(() => {
            el.textContent += text[i++];
            if (i >= text.length) { clearInterval(timer); setTimeout(cb, 1800); }
        }, 60);
    }

    function eraseWord(el, cb) {
        let text = el.textContent;
        const timer = setInterval(() => {
            text = text.slice(0, -1);
            el.textContent = text;
            if (!text.length) { clearInterval(timer); cb(); }
        }, 35);
    }

    function cycleWords() {
        wordIndex = (wordIndex + 1) % words.length;
        eraseWord(typingEl, () => {
            typeWord(typingEl, words[wordIndex], cycleWords);
        });
        heroWordEl.textContent = heroWords[wordIndex];
    }

    typeWord(typingEl, words[0], cycleWords);

    // Scroll-triggered fade-in
    const fadeEls = document.querySelectorAll('.fade-in');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((e, i) => {
            if (e.isIntersecting) {
                setTimeout(() => e.target.classList.add('visible'), i * 80);
                observer.unobserve(e.target);
            }
        });
    }, { threshold: 0.12 });
    fadeEls.forEach(el => observer.observe(el));

    // Count-up animation for stats
    const statEls = document.querySelectorAll('[data-count]');
    const statObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) { return; }
            const el = entry.target;
            const target = parseInt(el.dataset.count);
            const suffix = target >= 1000 ? 'K+' : target === 99 ? '%' : '+';
            const displayTarget = target >= 1000 ? target / 1000 : target;
            let current = 0;
            const step = displayTarget / 40;
            const timer = setInterval(() => {
                current = Math.min(current + step, displayTarget);
                el.textContent = (Number.isInteger(step) ? Math.floor(current) : current.toFixed(1)) + suffix;
                if (current >= displayTarget) { clearInterval(timer); }
            }, 35);
            statObserver.unobserve(el);
        });
    }, { threshold: 0.5 });
    statEls.forEach(el => statObserver.observe(el));
</script>
</body>
</html>
