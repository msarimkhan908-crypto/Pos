<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Under Review — SahulatPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Nastaliq+Urdu:wght@400;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: #0f172a;
            color: #f1f5f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
            position: relative;
            overflow: hidden;
        }

        /* Animated background orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .18;
            animation: float 8s ease-in-out infinite;
            pointer-events: none;
        }
        .orb-1 { width: 500px; height: 500px; background: #22c55e; top: -150px; left: -150px; animation-delay: 0s; }
        .orb-2 { width: 400px; height: 400px; background: #a855f7; bottom: -100px; right: -100px; animation-delay: 3s; }
        .orb-3 { width: 300px; height: 300px; background: #3b82f6; top: 40%; left: 60%; animation-delay: 6s; }
        @keyframes float { 0%,100%{transform:translateY(0) scale(1)} 50%{transform:translateY(-30px) scale(1.05)} }

        .card {
            position: relative;
            z-index: 1;
            background: rgba(30,41,59,.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }

        /* Pulsing clock icon */
        .icon-wrap {
            width: 80px; height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(34,197,94,.2), rgba(34,197,94,.05));
            border: 2px solid rgba(34,197,94,.35);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.75rem;
            animation: pulse-ring 2.5s ease-in-out infinite;
        }
        @keyframes pulse-ring {
            0%,100% { box-shadow: 0 0 0 0 rgba(34,197,94,.3); }
            50%      { box-shadow: 0 0 0 16px rgba(34,197,94,0); }
        }
        .icon-wrap svg { width: 36px; height: 36px; color: #22c55e; }

        .badge {
            display: inline-flex; align-items: center; gap: .4rem;
            background: rgba(251,191,36,.12);
            border: 1px solid rgba(251,191,36,.3);
            border-radius: 100px;
            padding: .3rem .9rem;
            font-size: .75rem; font-weight: 700; color: #fbbf24;
            letter-spacing: .06em; text-transform: uppercase;
            margin-bottom: 1.25rem;
        }
        .badge-dot {
            width: 6px; height: 6px; border-radius: 50%;
            background: #fbbf24;
            animation: blink 1.2s ease-in-out infinite;
        }
        @keyframes blink { 0%,100%{opacity:1} 50%{opacity:.2} }

        h1 { font-size: 1.6rem; font-weight: 800; letter-spacing: -.03em; margin-bottom: .6rem; }
        .sub { font-size: .925rem; color: #94a3b8; line-height: 1.65; margin-bottom: 2rem; }

        /* Steps */
        .steps { text-align: left; margin-bottom: 2rem; }
        .step-item {
            display: flex; align-items: flex-start; gap: .875rem;
            padding: .7rem 0;
            border-bottom: 1px solid rgba(255,255,255,.06);
        }
        .step-item:last-child { border-bottom: none; }
        .step-dot {
            width: 28px; height: 28px; flex-shrink: 0;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: .75rem; font-weight: 700;
        }
        .step-dot.done   { background: rgba(34,197,94,.2); color: #22c55e; border: 1.5px solid rgba(34,197,94,.4); }
        .step-dot.active { background: rgba(251,191,36,.15); color: #fbbf24; border: 1.5px solid rgba(251,191,36,.4); }
        .step-dot.wait   { background: rgba(148,163,184,.1); color: #64748b; border: 1.5px solid rgba(148,163,184,.2); }
        .step-text strong { font-size: .875rem; font-weight: 600; display: block; color: #f1f5f9; }
        .step-text span   { font-size: .8rem; color: #64748b; }

        /* Contact box */
        .contact {
            background: rgba(15,23,42,.5);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 14px;
            padding: 1rem 1.25rem;
            margin-bottom: 1.75rem;
            display: flex; align-items: center; gap: .875rem;
        }
        .contact-icon {
            width: 40px; height: 40px; flex-shrink: 0;
            background: rgba(34,197,94,.12);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .contact-icon svg { width: 20px; height: 20px; color: #22c55e; }
        .contact-text { text-align: left; }
        .contact-text p { font-size: .78rem; color: #64748b; }
        .contact-text strong { font-size: .9rem; color: #f1f5f9; font-weight: 600; }

        /* Auto-check status bar */
        .status-bar {
            display: flex; align-items: center; gap: .5rem;
            font-size: .78rem; color: #64748b;
            justify-content: center;
            margin-bottom: 1.5rem;
        }
        .status-bar svg { width: 14px; height: 14px; animation: spin 2s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        .btn-logout {
            background: transparent;
            border: 1px solid rgba(255,255,255,.12);
            color: #64748b;
            border-radius: 10px;
            padding: .6rem 1.5rem;
            font-size: .85rem; font-weight: 500;
            cursor: pointer; font-family: 'Inter', sans-serif;
            transition: all .18s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-logout:hover { border-color: rgba(255,255,255,.2); color: #94a3b8; }

        /* Activated overlay */
        #activated-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 100;
            background: rgba(15,23,42,.92);
            backdrop-filter: blur(12px);
            align-items: center; justify-content: center;
            flex-direction: column; gap: 1rem;
        }
        #activated-overlay.show { display: flex; }
        .activated-icon {
            width: 80px; height: 80px; border-radius: 50%;
            background: rgba(34,197,94,.2); border: 2px solid #22c55e;
            display: flex; align-items: center; justify-content: center;
            animation: pop .4s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes pop { from{transform:scale(0)} to{transform:scale(1)} }
        .activated-icon svg { width: 40px; height: 40px; color: #22c55e; }
        #activated-overlay h2 { font-size: 1.5rem; font-weight: 800; color: #f1f5f9; }
        #activated-overlay p  { font-size: .9rem; color: #94a3b8; }
    </style>
</head>
<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <!-- Activated overlay (shown on auto-detect) -->
    <div id="activated-overlay">
        <div class="activated-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
            </svg>
        </div>
        <h2>Account Activated!</h2>
        <p>Taking you to your dashboard…</p>
    </div>

    <div class="card">

        <div class="icon-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>

        <div class="badge">
            <span class="badge-dot"></span>
            Pending Activation
        </div>

        <h1>Payment Under Review</h1>
        <p class="sub">
            Thank you for signing up! We received your payment screenshot and are reviewing it now. Your account will be activated shortly.
        </p>

        <div class="steps">
            <div class="step-item">
                <div class="step-dot done">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                </div>
                <div class="step-text">
                    <strong>Account Created</strong>
                    <span>Your account has been registered successfully</span>
                </div>
            </div>
            <div class="step-item">
                <div class="step-dot done">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:14px;height:14px"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                </div>
                <div class="step-text">
                    <strong>Payment Proof Submitted</strong>
                    <span>Your Easypaisa screenshot was uploaded</span>
                </div>
            </div>
            <div class="step-item">
                <div class="step-dot active">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:14px;height:14px;animation:spin 2s linear infinite"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                </div>
                <div class="step-text">
                    <strong>Under Review</strong>
                    <span>We're verifying your payment — usually a few hours</span>
                </div>
            </div>
            <div class="step-item">
                <div class="step-dot wait">4</div>
                <div class="step-text">
                    <strong>Account Activated</strong>
                    <span>This page will automatically redirect you</span>
                </div>
            </div>
        </div>

        <div class="contact">
            <div class="contact-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                </svg>
            </div>
            <div class="contact-text">
                <p>Need faster activation? WhatsApp us:</p>
                <strong>0327-9754246</strong>
            </div>
        </div>

        <div class="status-bar" id="status-bar">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            <span id="status-text">Checking activation status…</span>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout">Sign out</button>
        </form>

    </div>

    <script>
        var CHECK_URL = '{{ route('account.status') }}';
        var DASHBOARD_URL = '/dashboard';
        var countdown = 30;

        function updateText() {
            document.getElementById('status-text').textContent =
                'Checking activation in ' + countdown + 's…';
            if (countdown > 0) countdown--;
        }

        function checkStatus() {
            countdown = 30;
            fetch(CHECK_URL, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.is_active) {
                        document.getElementById('activated-overlay').classList.add('show');
                        setTimeout(function() { window.location.href = DASHBOARD_URL; }, 2000);
                    }
                })
                .catch(function() {});
        }

        // Check immediately, then every 30 seconds
        checkStatus();
        setInterval(checkStatus, 30000);
        setInterval(updateText, 1000);
        updateText();
    </script>

</body>
</html>
