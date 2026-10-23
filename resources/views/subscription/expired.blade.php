<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Expired — SahulatPOS</title>
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

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: .15;
            animation: float 8s ease-in-out infinite;
            pointer-events: none;
        }
        .orb-1 { width: 500px; height: 500px; background: #ef4444; top: -150px; left: -150px; animation-delay: 0s; }
        .orb-2 { width: 400px; height: 400px; background: #f97316; bottom: -100px; right: -100px; animation-delay: 3s; }
        .orb-3 { width: 250px; height: 250px; background: #a855f7; top: 50%; left: 55%; animation-delay: 5s; }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-25px)} }

        .card {
            position: relative; z-index: 1;
            background: rgba(30,41,59,.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,.09);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            max-width: 460px; width: 100%;
            text-align: center;
        }

        .icon-wrap {
            width: 80px; height: 80px; border-radius: 50%;
            background: linear-gradient(135deg, rgba(239,68,68,.2), rgba(239,68,68,.05));
            border: 2px solid rgba(239,68,68,.35);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.75rem;
        }
        .icon-wrap svg { width: 36px; height: 36px; color: #ef4444; }

        .badge {
            display: inline-flex; align-items: center; gap: .4rem;
            background: rgba(239,68,68,.1);
            border: 1px solid rgba(239,68,68,.25);
            border-radius: 100px;
            padding: .3rem .9rem;
            font-size: .75rem; font-weight: 700; color: #f87171;
            letter-spacing: .06em; text-transform: uppercase;
            margin-bottom: 1.25rem;
        }

        h1 { font-size: 1.6rem; font-weight: 800; letter-spacing: -.03em; margin-bottom: .6rem; }
        .sub { font-size: .925rem; color: #94a3b8; line-height: 1.65; margin-bottom: 2rem; }

        /* Pricing reminder */
        .plans {
            display: grid; grid-template-columns: 1fr 1fr;
            gap: .75rem; margin-bottom: 1.75rem;
        }
        .plan-card {
            background: rgba(15,23,42,.6);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 14px; padding: 1rem;
            text-align: center;
        }
        .plan-card.highlight { border-color: rgba(34,197,94,.3); background: rgba(34,197,94,.06); }
        .plan-name { font-size: .75rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .35rem; }
        .plan-card.highlight .plan-name { color: #22c55e; }
        .plan-price { font-size: 1.25rem; font-weight: 800; color: #f1f5f9; letter-spacing: -.02em; }
        .plan-period { font-size: .72rem; color: #64748b; margin-top: .15rem; }

        /* Contact */
        .contact {
            background: rgba(15,23,42,.5);
            border: 1px solid rgba(255,255,255,.07);
            border-radius: 14px; padding: 1rem 1.25rem;
            margin-bottom: 1.75rem;
            display: flex; align-items: center; gap: .875rem;
        }
        .contact-icon {
            width: 40px; height: 40px; flex-shrink: 0;
            background: rgba(34,197,94,.12); border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .contact-icon svg { width: 20px; height: 20px; color: #22c55e; }
        .contact-text { text-align: left; }
        .contact-text p    { font-size: .78rem; color: #64748b; }
        .contact-text strong { font-size: .9rem; color: #f1f5f9; font-weight: 600; }

        .btn-logout {
            background: transparent;
            border: 1px solid rgba(255,255,255,.12);
            color: #64748b; border-radius: 10px;
            padding: .6rem 1.5rem;
            font-size: .85rem; font-weight: 500;
            cursor: pointer; font-family: 'Inter', sans-serif;
            transition: all .18s; text-decoration: none; display: inline-block;
        }
        .btn-logout:hover { border-color: rgba(255,255,255,.2); color: #94a3b8; }
    </style>
</head>
<body>

    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <div class="card">

        <div class="icon-wrap">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        </div>

        <div class="badge">Subscription Expired</div>

        <h1>Your Plan Has Expired</h1>
        <p class="sub">
            Your SahulatPOS subscription has ended. Renew now to continue accessing your POS, reports, and data — nothing is deleted.
        </p>

        <div class="plans">
            <div class="plan-card">
                <div class="plan-name">Monthly</div>
                <div class="plan-price">₨ 2,499</div>
                <div class="plan-period">per month</div>
            </div>
            <div class="plan-card highlight">
                <div class="plan-name">Annual ★</div>
                <div class="plan-price">₨ 14,999</div>
                <div class="plan-period">per year · best value</div>
            </div>
        </div>

        <div class="contact">
            <div class="contact-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                </svg>
            </div>
            <div class="contact-text">
                <p>To renew, send payment via Easypaisa:</p>
                <strong>0327-9754246 · Muhammad Sarim Khan</strong>
            </div>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn-logout">Sign out</button>
        </form>

    </div>

</body>
</html>
