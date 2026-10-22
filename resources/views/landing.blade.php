<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SahulatPOS — Restaurant POS System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap">
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; }

    :root {
      --bg:       #F7F6F3;
      --fg:       #161210;
      --primary:  #065F46;
      --primary-l:#D1FAE5;
      --accent:   #C2410C;
      --surface:  #FFFFFF;
      --muted:    #6B6560;
      --border:   #E2DDD8;
      --tag-bg:   #FEF3C7;
      --tag-fg:   #92400E;
      --font-display: 'Syne', system-ui, sans-serif;
      --font-body:    'DM Sans', system-ui, sans-serif;
      --r: 10px;
      --cap: 1100px;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        --bg:       #0F0D0B; --fg: #F2EFE9;
        --primary:  #10B981; --primary-l: #064E3B;
        --surface:  #1A1714; --muted: #9E9790;
        --border:   #2A2622; --tag-bg: #292110; --tag-fg: #FCD34D;
      }
    }

    body { background:var(--bg); color:var(--fg); font-family:var(--font-body); font-size:16px; line-height:1.6; padding:0 16px; }
    a { color:inherit; text-decoration:none; }
    .inner { max-width:var(--cap); margin:0 auto; }

    nav { position:sticky; top:0; z-index:20; background:color-mix(in srgb,var(--bg) 92%,transparent); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); }
    .nav-inner { max-width:var(--cap); margin:0 auto; display:flex; align-items:center; justify-content:space-between; padding:14px 0; }
    .logo { display:flex; align-items:baseline; gap:8px; }
    .logo-wordmark { font-family:var(--font-display); font-weight:800; font-size:1.25rem; color:var(--primary); letter-spacing:-.02em; }
    .logo-urdu { font-size:.9rem; color:var(--muted); }
    .nav-links { display:flex; gap:8px; }
    .btn-ghost { padding:8px 16px; border-radius:var(--r); font-size:.875rem; font-weight:500; color:var(--fg); background:transparent; border:1.5px solid var(--border); cursor:pointer; text-decoration:none; display:inline-block; transition:border-color .15s,background .15s; }
    .btn-ghost:hover { border-color:var(--primary); background:var(--primary-l); }
    .btn-primary { padding:8px 18px; border-radius:var(--r); font-size:.875rem; font-weight:600; color:#fff; background:var(--primary); border:none; cursor:pointer; text-decoration:none; display:inline-block; transition:opacity .15s; }
    .btn-primary:hover { opacity:.88; }

    .hero { padding:80px 0 64px; position:relative; overflow:hidden; }
    .urdu-bg { position:absolute; right:-40px; top:50%; transform:translateY(-50%); font-size:clamp(160px,22vw,280px); line-height:1; color:var(--primary); opacity:.05; user-select:none; pointer-events:none; font-weight:900; }
    .hero-content { max-width:620px; position:relative; }
    .hero-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; background:var(--tag-bg); color:var(--tag-fg); border-radius:99px; font-size:.8rem; font-weight:600; letter-spacing:.02em; text-transform:uppercase; margin-bottom:24px; }
    h1 { font-family:var(--font-display); font-size:clamp(2.2rem,5vw,3.5rem); font-weight:800; line-height:1.1; letter-spacing:-.03em; text-wrap:balance; margin-bottom:20px; }
    h1 em { font-style:normal; color:var(--primary); }
    .hero-sub { font-size:1.1rem; color:var(--muted); max-width:500px; margin-bottom:36px; line-height:1.65; }
    .hero-cta { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
    .btn-large { padding:13px 28px; font-size:1rem; border-radius:var(--r); font-weight:600; color:#fff; background:var(--primary); border:none; cursor:pointer; text-decoration:none; display:inline-block; transition:opacity .15s; }
    .btn-large:hover { opacity:.88; }
    .hero-note { font-size:.85rem; color:var(--muted); }
    .hero-stats { display:flex; gap:40px; margin-top:56px; flex-wrap:wrap; }
    .stat-num { font-family:var(--font-display); font-size:1.75rem; font-weight:800; color:var(--primary); }
    .stat-label { font-size:.8rem; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }

    .section { padding:72px 0; }
    .section-label { font-size:.75rem; font-weight:700; letter-spacing:.1em; text-transform:uppercase; color:var(--primary); margin-bottom:12px; }
    h2 { font-family:var(--font-display); font-size:clamp(1.75rem,3.5vw,2.5rem); font-weight:700; line-height:1.15; letter-spacing:-.025em; text-wrap:balance; margin-bottom:16px; }
    .section-sub { font-size:1.05rem; color:var(--muted); max-width:540px; line-height:1.6; margin-bottom:48px; }

    .features-bg { background:var(--surface); border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
    .features-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:24px; }
    .feat-card { padding:28px 24px; background:var(--bg); border:1.5px solid var(--border); border-radius:calc(var(--r)*1.5); }
    .feat-icon { width:44px; height:44px; background:var(--primary-l); color:var(--primary); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.2rem; margin-bottom:20px; }
    .feat-title { font-family:var(--font-display); font-size:1.05rem; font-weight:700; margin-bottom:8px; }
    .feat-desc { font-size:.9rem; color:var(--muted); line-height:1.6; }

    .steps { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:0; margin-top:48px; position:relative; }
    .steps::before { content:''; position:absolute; top:22px; left:22px; right:22px; height:1px; background:var(--border); }
    .step { padding:0 12px 0 0; position:relative; z-index:1; }
    .step-num { width:44px; height:44px; border-radius:50%; background:var(--primary); color:#fff; font-family:var(--font-display); font-size:.9rem; font-weight:800; display:flex; align-items:center; justify-content:center; margin-bottom:16px; }
    .step-title { font-family:var(--font-display); font-size:1rem; font-weight:700; margin-bottom:6px; }
    .step-desc { font-size:.875rem; color:var(--muted); line-height:1.55; }

    .pricing-bg { background:var(--surface); border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
    .pricing-toggle { display:flex; align-items:center; gap:12px; margin-bottom:40px; flex-wrap:wrap; }
    .pill-toggle { display:flex; background:var(--bg); border:1.5px solid var(--border); border-radius:99px; padding:3px; }
    .pill-btn { padding:5px 16px; border-radius:99px; font-size:.85rem; font-weight:600; border:none; background:transparent; color:var(--muted); cursor:pointer; transition:all .15s; font-family:var(--font-body); }
    .pill-btn.active { background:var(--primary); color:#fff; }
    .save-tag { padding:3px 10px; background:var(--tag-bg); color:var(--tag-fg); border-radius:99px; font-size:.75rem; font-weight:700; }
    .pricing-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px; max-width:680px; }
    .plan-card { padding:32px 28px; border:1.5px solid var(--border); border-radius:calc(var(--r)*1.5); background:var(--bg); }
    .plan-card.popular { border-color:var(--primary); background:var(--surface); position:relative; }
    .popular-badge { position:absolute; top:-12px; left:50%; transform:translateX(-50%); background:var(--primary); color:#fff; font-size:.75rem; font-weight:700; padding:3px 14px; border-radius:99px; letter-spacing:.04em; text-transform:uppercase; white-space:nowrap; }
    .plan-name { font-family:var(--font-display); font-size:1rem; font-weight:700; color:var(--muted); text-transform:uppercase; letter-spacing:.08em; margin-bottom:12px; }
    .plan-price { display:flex; align-items:baseline; gap:4px; margin-bottom:6px; }
    .price-pkr { font-size:.9rem; font-weight:600; color:var(--muted); }
    .price-num { font-family:var(--font-display); font-size:2.6rem; font-weight:800; letter-spacing:-.03em; font-variant-numeric:tabular-nums; }
    .price-period { font-size:.875rem; color:var(--muted); }
    .plan-note { font-size:.8rem; color:var(--muted); margin-bottom:24px; }
    .plan-features { list-style:none; padding:0; display:flex; flex-direction:column; gap:10px; margin-bottom:28px; }
    .plan-features li { font-size:.9rem; display:flex; align-items:center; gap:10px; }
    .plan-features li::before { content:'✓'; width:18px; height:18px; background:var(--primary-l); color:var(--primary); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.7rem; font-weight:800; flex-shrink:0; }
    .btn-plan { width:100%; padding:12px; border-radius:var(--r); font-size:.95rem; font-weight:600; font-family:var(--font-body); cursor:pointer; border:1.5px solid var(--primary); background:transparent; color:var(--primary); transition:all .15s; text-align:center; display:block; text-decoration:none; }
    .btn-plan:hover,.plan-card.popular .btn-plan { background:var(--primary); color:#fff; }
    .payments { display:flex; align-items:center; gap:8px; margin-top:20px; flex-wrap:wrap; }
    .pay-chip { padding:4px 12px; background:var(--bg); border:1.5px solid var(--border); border-radius:99px; font-size:.75rem; font-weight:600; color:var(--muted); }

    .cta-band { background:var(--primary); padding:72px 16px; text-align:center; color:#fff; }
    .cta-band h2 { color:#fff; margin-bottom:12px; }
    .cta-band p { color:rgba(255,255,255,.75); font-size:1.05rem; margin-bottom:32px; }
    .btn-white { display:inline-block; padding:13px 32px; background:#fff; color:var(--primary); border-radius:var(--r); font-size:1rem; font-weight:700; font-family:var(--font-body); text-decoration:none; transition:opacity .15s; }
    .btn-white:hover { opacity:.9; }
    .cta-fine { margin-top:14px; font-size:.85rem; color:rgba(255,255,255,.6); }

    footer { border-top:1px solid var(--border); padding:32px 0; }
    .footer-inner { max-width:var(--cap); margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
    .footer-copy { font-size:.825rem; color:var(--muted); }
    .footer-links { display:flex; gap:20px; }
    .footer-links a { font-size:.825rem; color:var(--muted); transition:color .15s; }
    .footer-links a:hover { color:var(--fg); }
    .divider { height:1px; background:var(--border); }

    @media (max-width:600px) {
      .hero { padding:48px 0 36px; }
      .hero-stats { gap:24px; }
      .steps::before { display:none; }
      .step { padding-right:0; }
      .nav-links .btn-ghost { display:none; }
    }
    </style>
</head>
<body>

<nav>
  <div class="nav-inner inner">
    <a href="{{ route('home') }}" class="logo">
      <span class="logo-wordmark">SahulatPOS</span>
      <span class="logo-urdu">سہولت</span>
    </a>
    <div class="nav-links">
      <a href="{{ route('login') }}" class="btn-ghost">Sign in</a>
      <a href="{{ route('register') }}" class="btn-primary">Start Free Trial</a>
    </div>
  </div>
</nav>

<section class="hero">
  <div class="inner">
    <div class="hero-content">
      <div class="hero-badge">✦ 14-day free trial — no credit card</div>
      <h1>Run your restaurant <em>with ease.</em></h1>
      <p class="hero-sub">SahulatPOS gives your restaurant a complete point-of-sale system: fast order entry, kitchen tickets, daily reports, and flexible payments — all in one place.</p>
      <div class="hero-cta">
        <a href="{{ route('register') }}" class="btn-large">Start Free Trial</a>
        <span class="hero-note">Already have an account? <a href="{{ route('login') }}" style="color:var(--primary);font-weight:600">Sign in →</a></span>
      </div>
      <div class="hero-stats">
        <div class="stat-item"><div class="stat-num">14</div><div class="stat-label">Day Free Trial</div></div>
        <div class="stat-item"><div class="stat-num">5</div><div class="stat-label">Payment Methods</div></div>
        <div class="stat-item"><div class="stat-num">∞</div><div class="stat-label">Products & Staff</div></div>
      </div>
    </div>
  </div>
  <div class="urdu-bg" aria-hidden="true">سہولت</div>
</section>

<div class="divider"></div>

<section class="section features-bg">
  <div class="inner">
    <p class="section-label">What you get</p>
    <h2>Everything a restaurant needs</h2>
    <p class="section-sub">Built for restaurants, cafes, and fast-food counters in Pakistan. No complicated setup.</p>
    <div class="features-grid">
      <div class="feat-card"><div class="feat-icon">🖥️</div><div class="feat-title">Fast POS Terminal</div><p class="feat-desc">Take orders quickly with a clean touch-friendly interface. Dine-in, takeaway, and delivery in one screen.</p></div>
      <div class="feat-card"><div class="feat-icon">🍳</div><div class="feat-title">Kitchen Order Tickets</div><p class="feat-desc">KOTs sent instantly to the kitchen. Deals print as one item — no confusion, no wasted paper.</p></div>
      <div class="feat-card"><div class="feat-icon">📊</div><div class="feat-title">Sales Reports</div><p class="feat-desc">Daily sales, top-selling items, and revenue trends. See how your business is doing at a glance.</p></div>
      <div class="feat-card"><div class="feat-icon">💳</div><div class="feat-title">All Payment Methods</div><p class="feat-desc">Cash, card, Easypaisa, and JazzCash — accept however your customer wants to pay.</p></div>
      <div class="feat-card"><div class="feat-icon">👥</div><div class="feat-title">Staff Management</div><p class="feat-desc">Add cashiers, managers, and kitchen staff. Control who can access what with role-based permissions.</p></div>
      <div class="feat-card"><div class="feat-icon">🔧</div><div class="feat-title">Deals & Modifiers</div><p class="feat-desc">Create meal deals, add-ons, and variants. Menu changes take seconds.</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="inner">
    <p class="section-label">Getting started</p>
    <h2>Up and running in minutes</h2>
    <p class="section-sub">No IT team. No installation. Open your browser and go.</p>
    <div class="steps">
      <div class="step"><div class="step-num">1</div><div class="step-title">Sign up free</div><p class="step-desc">Create your account with your restaurant name and email. Your 14-day trial starts immediately.</p></div>
      <div class="step"><div class="step-num">2</div><div class="step-title">Add your menu</div><p class="step-desc">Add categories, products, deals, and prices. Takes about 10–20 minutes for most menus.</p></div>
      <div class="step"><div class="step-num">3</div><div class="step-title">Invite your team</div><p class="step-desc">Add your cashiers and managers. They log in from any device — tablet, laptop, or PC.</p></div>
      <div class="step"><div class="step-num">4</div><div class="step-title">Start taking orders</div><p class="step-desc">Your POS is ready. Take your first order, print the receipt, and watch the KOT appear in the kitchen.</p></div>
    </div>
  </div>
</section>

<section class="section pricing-bg" id="pricing">
  <div class="inner">
    <p class="section-label">Pricing</p>
    <h2>Simple, honest pricing</h2>
    <p class="section-sub">One plan, all features. Pay monthly or save 2 months by going annual.</p>
    <div class="pricing-toggle">
      <div class="pill-toggle">
        <button class="pill-btn active" id="btn-monthly" onclick="setFreq('monthly')">Monthly</button>
        <button class="pill-btn" id="btn-annual" onclick="setFreq('annual')">Annual</button>
      </div>
      <span class="save-tag">Save 2 months</span>
    </div>
    <div class="pricing-grid">
      <div class="plan-card popular">
        <span class="popular-badge">All Features Included</span>
        <div class="plan-name">Full Access</div>
        <div class="plan-price">
          <span class="price-pkr">PKR</span>
          <span class="price-num" id="price-num">2,499</span>
          <span class="price-period" id="price-period">/month</span>
        </div>
        <p class="plan-note" id="plan-note">Billed monthly. Cancel any time.</p>
        <ul class="plan-features">
          <li>Unlimited products &amp; categories</li>
          <li>Kitchen order tickets (KOT)</li>
          <li>Deals, variants &amp; modifiers</li>
          <li>Cash, card, Easypaisa, JazzCash</li>
          <li>Daily &amp; monthly reports</li>
          <li>Unlimited staff accounts</li>
          <li>Table &amp; dine-in management</li>
          <li>Customer receipts &amp; reprints</li>
        </ul>
        <a href="{{ route('register') }}" class="btn-plan">Start 14-Day Free Trial</a>
      </div>
    </div>
    <div class="payments">
      <span style="font-size:.8rem;color:var(--muted);font-weight:600">Subscription via:</span>
      <span class="pay-chip">Bank Transfer</span>
      <span class="pay-chip">JazzCash</span>
      <span class="pay-chip">Easypaisa</span>
    </div>
  </div>
</section>

<section class="cta-band">
  <div class="inner">
    <h2>Ready to modernize your restaurant?</h2>
    <p>Start your free 14-day trial today. No credit card, no setup fees.</p>
    <a href="{{ route('register') }}" class="btn-white">Create Your Account</a>
    <p class="cta-fine">After the trial, plans start at PKR 2,499/month.</p>
  </div>
</section>

<footer>
  <div class="footer-inner">
    <div>
      <div class="logo" style="margin-bottom:4px">
        <span class="logo-wordmark">SahulatPOS</span>
        <span class="logo-urdu">سہولت</span>
      </div>
      <p class="footer-copy">© {{ date('Y') }} SahulatPOS. Made for Pakistani restaurants.</p>
    </div>
    <div class="footer-links">
      <a href="{{ route('login') }}">Sign in</a>
      <a href="{{ route('register') }}">Sign up</a>
      <a href="#pricing">Pricing</a>
    </div>
  </div>
</footer>

<script>
const monthly = { num: '2,499', period: '/month', note: 'Billed monthly. Cancel any time.' };
const annual  = { num: '24,990', period: '/year', note: 'Billed once a year. Saves PKR 5,000.' };
function setFreq(freq) {
  const data = freq === 'annual' ? annual : monthly;
  document.getElementById('price-num').textContent    = data.num;
  document.getElementById('price-period').textContent = data.period;
  document.getElementById('plan-note').textContent    = data.note;
  document.getElementById('btn-monthly').classList.toggle('active', freq === 'monthly');
  document.getElementById('btn-annual').classList.toggle('active', freq === 'annual');
}
</script>

</body>
</html>
