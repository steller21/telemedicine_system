<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MediConnect — Smart Telemedicine System</title>
    <link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --teal:      #0EB8A0;
            --teal-dark: #0A8A78;
            --navy:      #0B1526;
            --navy-mid:  #112035;
            --navy-light:#1A3050;
            --cream:     #F5F0E8;
            --cream-dim: #EDE8DF;
            --white:     #FFFFFF;
            --muted:     #8A9BB0;
            --accent:    #FF6B4A;
        }
 
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
 
        html { scroll-behavior: smooth; }
 
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--navy);
            color: var(--white);
            overflow-x: hidden;
        }
 
        /* ── NOISE TEXTURE OVERLAY ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.04'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 0;
            opacity: 0.4;
        }
 
        /* ── ANIMATED GRADIENT BACKGROUND ── */
        .bg-gradient {
            position: fixed;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(ellipse 80% 60% at 20% 10%, rgba(14,184,160,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 80%, rgba(14,184,160,0.08) 0%, transparent 50%),
                radial-gradient(ellipse 40% 40% at 50% 50%, rgba(255,107,74,0.04) 0%, transparent 60%),
                var(--navy);
            animation: bgPulse 8s ease-in-out infinite alternate;
        }
        @keyframes bgPulse {
            0%   { opacity: 1; }
            100% { opacity: 0.85; }
        }
 
        /* ── NAV ── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 60px;
            backdrop-filter: blur(20px);
            background: rgba(11,21,38,0.7);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            animation: slideDown 0.8s ease both;
        }
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
 
        .logo {
            font-family: 'Clash Display', sans-serif;
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--white);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo-dot {
            width: 10px; height: 10px;
            background: var(--teal);
            border-radius: 50%;
            box-shadow: 0 0 12px var(--teal);
            animation: blink 2s ease-in-out infinite;
        }
        @keyframes blink {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.5; transform: scale(0.8); }
        }
 
        .nav-links {
            display: flex;
            align-items: center;
            gap: 36px;
            list-style: none;
        }
        .nav-links a {
            color: var(--muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
            letter-spacing: 0.02em;
        }
        .nav-links a:hover { color: var(--white); }
 
        .nav-cta {
            background: var(--teal);
            color: var(--navy) !important;
            padding: 10px 22px;
            border-radius: 50px;
            font-weight: 600 !important;
            transition: background 0.2s, transform 0.2s !important;
        }
        .nav-cta:hover {
            background: var(--teal-dark) !important;
            transform: translateY(-1px);
            color: var(--white) !important;
        }
 
        /* ── HERO ── */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 140px 60px 80px;
            position: relative;
            overflow: hidden;
        }
 
        .hero-content {
            max-width: 680px;
            z-index: 1;
        }
 
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(14,184,160,0.1);
            border: 1px solid rgba(14,184,160,0.3);
            color: var(--teal);
            font-size: 0.8rem;
            font-weight: 500;
            padding: 7px 16px;
            border-radius: 50px;
            margin-bottom: 32px;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            animation: fadeUp 0.8s 0.2s ease both;
        }
        .hero-badge::before {
            content: '';
            width: 6px; height: 6px;
            background: var(--teal);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--teal);
        }
 
        .hero-title {
            font-family: 'Clash Display', sans-serif;
            font-size: clamp(2.8rem, 6vw, 5rem);
            font-weight: 600;
            line-height: 1.05;
            margin-bottom: 12px;
            animation: fadeUp 0.8s 0.35s ease both;
        }
        .hero-title em {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            color: var(--teal);
        }
 
        .hero-subtitle {
            font-size: clamp(1rem, 2vw, 1.25rem);
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 48px;
            max-width: 500px;
            font-weight: 300;
            animation: fadeUp 0.8s 0.5s ease both;
        }
 
        .hero-actions {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            animation: fadeUp 0.8s 0.65s ease both;
        }
 
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--teal);
            color: var(--navy);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            padding: 16px 32px;
            border-radius: 50px;
            transition: all 0.25s;
            box-shadow: 0 0 30px rgba(14,184,160,0.3);
        }
        .btn-primary:hover {
            background: var(--teal-dark);
            color: var(--white);
            transform: translateY(-2px);
            box-shadow: 0 0 50px rgba(14,184,160,0.4);
        }
        .btn-primary svg { transition: transform 0.25s; }
        .btn-primary:hover svg { transform: translateX(4px); }
 
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: transparent;
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 16px 32px;
            border-radius: 50px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.25s;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.4);
            transform: translateY(-2px);
        }
 
        /* ── HERO VISUAL (right side) ── */
        .hero-visual {
            position: absolute;
            right: -80px;
            top: 50%;
            transform: translateY(-50%);
            width: 580px;
            height: 580px;
            animation: fadeUp 1s 0.4s ease both;
        }
 
        .hero-card-main {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 340px;
            background: var(--navy-mid);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 28px;
            backdrop-filter: blur(20px);
            box-shadow: 0 40px 80px rgba(0,0,0,0.4);
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translate(-50%, -52%); }
            50%       { transform: translate(-50%, -48%); }
        }
 
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .card-avatar {
            width: 44px; height: 44px;
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }
        .card-name { font-weight: 600; font-size: 0.95rem; }
        .card-role { font-size: 0.75rem; color: var(--muted); }
        .card-status {
            margin-left: auto;
            background: rgba(14,184,160,0.15);
            color: var(--teal);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 50px;
            letter-spacing: 0.05em;
        }
 
        .video-preview {
            background: var(--navy);
            border-radius: 14px;
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .video-preview::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 50% 50%, rgba(14,184,160,0.08), transparent 70%);
        }
        .video-icon {
            font-size: 2.5rem;
            z-index: 1;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50%       { transform: scale(1.1); }
        }
        .video-label {
            position: absolute;
            bottom: 10px; left: 12px;
            font-size: 0.7rem;
            color: var(--muted);
            background: rgba(0,0,0,0.5);
            padding: 3px 8px;
            border-radius: 4px;
        }
 
        .card-actions {
            display: flex;
            gap: 8px;
        }
        .card-btn {
            flex: 1;
            padding: 10px;
            border-radius: 10px;
            font-size: 0.8rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            border: none;
        }
        .card-btn-accept {
            background: var(--teal);
            color: var(--navy);
        }
        .card-btn-decline {
            background: rgba(255,107,74,0.15);
            color: var(--accent);
        }
 
        /* Floating mini cards */
        .mini-card {
            position: absolute;
            background: var(--navy-mid);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 14px 18px;
            backdrop-filter: blur(20px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: floatAlt 5s ease-in-out infinite;
        }
        @keyframes floatAlt {
            0%, 100% { transform: translateY(0); }
            50%       { transform: translateY(-10px); }
        }
        .mini-card-1 {
            top: 60px; left: 20px;
            animation-delay: -2s;
        }
        .mini-card-2 {
            bottom: 80px; right: 10px;
            animation-delay: -1s;
        }
        .mini-label {
            font-size: 0.7rem;
            color: var(--muted);
            margin-bottom: 4px;
        }
        .mini-value {
            font-family: 'Clash Display', sans-serif;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--teal);
        }
        .mini-sub {
            font-size: 0.7rem;
            color: var(--muted);
        }
 
        /* Ring decoration */
        .hero-ring {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 500px; height: 500px;
            border-radius: 50%;
            border: 1px solid rgba(14,184,160,0.08);
        }
        .hero-ring-2 {
            width: 380px; height: 380px;
            border-color: rgba(14,184,160,0.12);
        }
 
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
 
        /* ── STATS STRIP ── */
        .stats-strip {
            padding: 40px 60px;
            border-top: 1px solid rgba(255,255,255,0.06);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            justify-content: center;
            gap: 80px;
            flex-wrap: wrap;
            background: rgba(255,255,255,0.02);
        }
        .stat-item { text-align: center; }
        .stat-num {
            font-family: 'Clash Display', sans-serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--teal);
            display: block;
        }
        .stat-label {
            font-size: 0.8rem;
            color: var(--muted);
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
 
        /* ── FEATURES ── */
        .features {
            padding: 120px 60px;
            max-width: 1200px;
            margin: 0 auto;
        }
 
        .section-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--teal);
            font-weight: 600;
            margin-bottom: 16px;
        }
        .section-title {
            font-family: 'Clash Display', sans-serif;
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 600;
            line-height: 1.15;
            margin-bottom: 60px;
            max-width: 500px;
        }
        .section-title em {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            color: var(--teal);
        }
 
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
 
        .feature-card {
            background: var(--navy-mid);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 20px;
            padding: 32px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--teal), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .feature-card:hover {
            border-color: rgba(14,184,160,0.2);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }
        .feature-card:hover::before { opacity: 1; }
 
        .feature-card.featured {
            background: linear-gradient(135deg, rgba(14,184,160,0.12), rgba(14,184,160,0.04));
            border-color: rgba(14,184,160,0.2);
            grid-row: span 2;
        }
 
        .feature-icon {
            width: 48px; height: 48px;
            background: rgba(14,184,160,0.12);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 20px;
        }
        .feature-title {
            font-family: 'Clash Display', sans-serif;
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .feature-desc {
            font-size: 0.875rem;
            color: var(--muted);
            line-height: 1.7;
        }
 
        /* ── HOW IT WORKS ── */
        .how-it-works {
            padding: 80px 60px 120px;
            max-width: 1200px;
            margin: 0 auto;
        }
 
        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0;
            position: relative;
            margin-top: 60px;
        }
        .steps::before {
            content: '';
            position: absolute;
            top: 28px;
            left: calc(12.5% + 24px);
            right: calc(12.5% + 24px);
            height: 1px;
            background: linear-gradient(90deg, var(--teal), rgba(14,184,160,0.2), var(--teal), rgba(14,184,160,0.2));
            z-index: 0;
        }
 
        .step {
            text-align: center;
            padding: 0 20px;
            position: relative;
            z-index: 1;
        }
        .step-num {
            width: 56px; height: 56px;
            background: var(--navy-mid);
            border: 2px solid var(--teal);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Clash Display', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--teal);
            margin: 0 auto 20px;
            position: relative;
            box-shadow: 0 0 20px rgba(14,184,160,0.2);
        }
        .step-title {
            font-family: 'Clash Display', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .step-desc {
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.6;
        }
 
        /* ── CTA ── */
        .cta-section {
            padding: 80px 60px 120px;
        }
        .cta-box {
            max-width: 900px;
            margin: 0 auto;
            background: linear-gradient(135deg, var(--navy-mid), var(--navy-light));
            border: 1px solid rgba(14,184,160,0.2);
            border-radius: 32px;
            padding: 80px 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cta-box::before {
            content: '';
            position: absolute;
            top: -100px; left: 50%;
            transform: translateX(-50%);
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(14,184,160,0.1), transparent 60%);
            pointer-events: none;
        }
        .cta-title {
            font-family: 'Clash Display', sans-serif;
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            font-weight: 600;
            margin-bottom: 16px;
            line-height: 1.2;
        }
        .cta-title em {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            color: var(--teal);
        }
        .cta-sub {
            color: var(--muted);
            font-size: 1rem;
            margin-bottom: 40px;
        }
        .cta-btns {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
 
        /* ── FOOTER ── */
        footer {
            padding: 40px 60px;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }
        .footer-logo {
            font-family: 'Clash Display', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--muted);
        }
        .footer-text {
            font-size: 0.8rem;
            color: var(--muted);
        }
 
        /* ── SCROLL ANIMATIONS ── */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }
 
        /* ── RESPONSIVE ── */
        @media (max-width: 1024px) {
            .hero-visual { display: none; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .feature-card.featured { grid-row: span 1; }
            .steps { grid-template-columns: repeat(2, 1fr); gap: 40px; }
            .steps::before { display: none; }
            nav { padding: 20px 30px; }
            .hero { padding: 140px 30px 80px; }
            .features, .how-it-works, .cta-section { padding-left: 30px; padding-right: 30px; }
        }
        @media (max-width: 600px) {
            .features-grid { grid-template-columns: 1fr; }
            .steps { grid-template-columns: 1fr; }
            .nav-links { display: none; }
            .stats-strip { gap: 40px; padding: 30px; }
        }
    </style>
</head>
<body>
 
<div class="bg-gradient"></div>
 
<!-- NAV -->
<nav>
    <a href="index.php" class="logo">
        <div class="logo-dot"></div>
        MediConnect
    </a>
    <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#how">How It Works</a></li>
        <li><a href="login.php">Sign In</a></li>
        <li><a href="register.php" class="nav-cta">Get Started</a></li>
    </ul>
</nav>
 
<!-- HERO -->
<section class="hero">
    <div class="hero-content">
        <div class="hero-badge">Smart Telemedicine Platform</div>
 
        <h1 class="hero-title">
            Healthcare,<br>
            <em>reimagined</em><br>
            for everyone.
        </h1>
 
        <p class="hero-subtitle">
            Connect with doctors instantly, manage your medicines, monitor your health — all from one intelligent platform built for modern care.
        </p>
 
        <div class="hero-actions">
            <a href="register.php" class="btn-primary">
                Start for Free
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
            <a href="login.php" class="btn-secondary">
                Sign In
            </a>
        </div>
    </div>
 
    <!-- Hero Visual -->
    <div class="hero-visual">
        <div class="hero-ring"></div>
        <div class="hero-ring hero-ring-2"></div>
 
        <!-- Main card -->
        <div class="hero-card-main">
            <div class="card-header">
                <div class="card-avatar">👨‍⚕️</div>
                <div>
                    <div class="card-name">Dr. Sharma</div>
                    <div class="card-role">General Physician</div>
                </div>
                <div class="card-status">● LIVE</div>
            </div>
            <div class="video-preview">
                <div class="video-icon">🎥</div>
                <div class="video-label">Video Consultation</div>
            </div>
            <div class="card-actions">
                <button class="card-btn card-btn-accept">✅ Accept Call</button>
                <button class="card-btn card-btn-decline">❌ Decline</button>
            </div>
        </div>
 
        <!-- Mini cards -->
        <div class="mini-card mini-card-1">
            <div class="mini-label">Next Appointment</div>
            <div class="mini-value">2:30 PM</div>
            <div class="mini-sub">Today · Dr. Sharma</div>
        </div>
        <div class="mini-card mini-card-2">
            <div class="mini-label">Medicines Due</div>
            <div class="mini-value">3</div>
            <div class="mini-sub">⏰ Reminders active</div>
        </div>
    </div>
</section>
 
<!-- STATS -->
<div class="stats-strip reveal">
    <div class="stat-item">
        <span class="stat-num">24/7</span>
        <span class="stat-label">Doctor Availability</span>
    </div>
    <div class="stat-item">
        <span class="stat-num">100%</span>
        <span class="stat-label">Secure & Private</span>
    </div>
    <div class="stat-item">
        <span class="stat-num">&lt;30s</span>
        <span class="stat-label">Connection Time</span>
    </div>
    <div class="stat-item">
        <span class="stat-num">HD</span>
        <span class="stat-label">Video Quality</span>
    </div>
</div>
 
<!-- FEATURES -->
<section class="features" id="features">
    <div class="reveal">
        <div class="section-label">What We Offer</div>
        <h2 class="section-title">Everything you need for <em>complete care</em></h2>
    </div>
 
    <div class="features-grid">
        <div class="feature-card featured reveal">
            <div class="feature-icon">🎥</div>
            <div class="feature-title">HD Video Consultations</div>
            <div class="feature-desc">
                Connect with your doctor face-to-face from anywhere. Our WebRTC-powered video calls work on any device — phone, tablet, or laptop — with crystal clear quality and no app download needed.
                <br><br>
                Doctors receive instant popup alerts the moment a patient calls, with one-click accept to jump straight into the consultation.
            </div>
        </div>
 
        <div class="feature-card reveal">
            <div class="feature-icon">💊</div>
            <div class="feature-title">Medicine Checklist</div>
            <div class="feature-desc">Track your daily medicines with reminders, dosage details, and intake confirmations. Never miss a dose again.</div>
        </div>
 
        <div class="feature-card reveal">
            <div class="feature-icon">📅</div>
            <div class="feature-title">Appointment Booking</div>
            <div class="feature-desc">Browse available doctors and book appointments in seconds. Receive real-time call alerts when it's your turn.</div>
        </div>
 
        <div class="feature-card reveal">
            <div class="feature-icon">👁️</div>
            <div class="feature-title">Patient Monitoring</div>
            <div class="feature-desc">Allow trusted family members or caregivers to monitor your health activity and medicine adherence remotely.</div>
        </div>
 
        <div class="feature-card reveal">
            <div class="feature-icon">🤖</div>
            <div class="feature-title">AI Health Assistant</div>
            <div class="feature-desc">Get instant answers to health questions with our intelligent chatbot, available round the clock.</div>
        </div>
 
        <div class="feature-card reveal">
            <div class="feature-icon">📄</div>
            <div class="feature-title">Report Uploads</div>
            <div class="feature-desc">Share lab reports and medical documents securely with your doctor before or after a consultation.</div>
        </div>
    </div>
</section>
 
<!-- HOW IT WORKS -->
<section class="how-it-works" id="how">
    <div class="reveal">
        <div class="section-label">The Process</div>
        <h2 class="section-title">From signup to <em>first call</em> in minutes</h2>
    </div>
 
    <div class="steps">
        <div class="step reveal">
            <div class="step-num">01</div>
            <div class="step-title">Create Account</div>
            <div class="step-desc">Register as a patient or doctor in under a minute. No complex forms.</div>
        </div>
        <div class="step reveal">
            <div class="step-num">02</div>
            <div class="step-title">Book Appointment</div>
            <div class="step-desc">Choose a doctor and pick a date and time that works for you.</div>
        </div>
        <div class="step reveal">
            <div class="step-num">03</div>
            <div class="step-title">Start Video Call</div>
            <div class="step-desc">Click "Start Call" from your dashboard. The doctor gets an instant alert.</div>
        </div>
        <div class="step reveal">
            <div class="step-num">04</div>
            <div class="step-title">Get Care</div>
            <div class="step-desc">Receive prescriptions, follow-ups, and manage your health all in one place.</div>
        </div>
    </div>
</section>
 
<!-- CTA -->
<section class="cta-section">
    <div class="cta-box reveal">
        <h2 class="cta-title">Ready to experience <em>better healthcare?</em></h2>
        <p class="cta-sub">Join patients and doctors already using MediConnect.</p>
        <div class="cta-btns">
            <a href="register.php?role=patient" class="btn-primary">
                I'm a Patient
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
            </a>
            <a href="register.php?role=doctor" class="btn-secondary">I'm a Doctor</a>
        </div>
    </div>
</section>
 
<!-- FOOTER -->
<footer>
    <div class="footer-logo">MediConnect</div>
    <div class="footer-text">Smart Telemedicine & Emergency Assistance System</div>
    <div class="footer-text">© 2026 · Built with ❤️ for better healthcare</div>
</footer>
 
<script>
// Scroll reveal
const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, i) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.classList.add('visible');
            }, 100);
            observer.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });
 
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
 
// Stagger feature cards
document.querySelectorAll('.feature-card, .step').forEach((el, i) => {
    el.style.transitionDelay = (i * 0.1) + 's';
});
</script>
 
</body>
</html>