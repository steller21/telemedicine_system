<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MediConnect — Smart telemedicine platform. Connect with doctors instantly via HD video calls, manage prescriptions, and monitor your health.">
    <title>MediConnect — Smart Telemedicine Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --teal:      #0EB8A0;
            --teal-dark: #0A8A78;
            --teal-light:#14D9BD;
            --navy:      #f8fafc;
            --navy-mid:  #ffffff;
            --navy-light:#f1f5f9;
            --navy-card: #ffffff;
            --cream:     #F5F0E8;
            --white:     #1e293b;
            --muted:     #64748b;
            --muted-dim: #94a3b8;
            --accent:    #FF6B4A;
            --border:    rgba(0,0,0,0.08);
            --border-light: rgba(0,0,0,0.1);
            --font-display: 'Clash Display', sans-serif;
            --font-body:    'DM Sans', sans-serif;
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
        }

        body.dark-mode {
            --navy:      #0B1526;
            --navy-mid:  #112035;
            --navy-light:#1A3050;
            --navy-card: #0F1E36;
            --white:     #FFFFFF;
            --muted:     #7A8EA8;
            --muted-dim: #4A5E78;
            --border:    rgba(255,255,255,0.07);
            --border-light: rgba(255,255,255,0.12);
        }
        body.dark-mode .bg-gradient { 
            background: radial-gradient(ellipse 80% 60% at 20% 10%, rgba(14,184,160,0.12) 0%, transparent 60%),
                        radial-gradient(ellipse 60% 50% at 80% 80%, rgba(14,184,160,0.08) 0%, transparent 50%),
                        var(--navy);
        }
        body:not(.dark-mode)::before { opacity: 0.01; }
 
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; -webkit-font-smoothing: antialiased; }
 
        body {
            font-family: var(--font-body);
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
            padding: 18px 60px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            background: rgba(11,21,38,0.75);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            animation: slideDown 0.8s ease both;
        }
        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to   { transform: translateY(0);     opacity: 1; }
        }
 
        .logo {
            font-family: var(--font-display);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--white);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-right: 15px;
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
            background: var(--teal) !important;
            color: var(--navy) !important;
            padding: 10px 24px !important;
            border-radius: 50px;
            font-weight: 600 !important;
            transition: all 0.25s !important;
            box-shadow: 0 0 20px rgba(14,184,160,0.2);
        }
        .nav-cta:hover {
            background: var(--teal-dark) !important;
            color: var(--white) !important;
            transform: translateY(-1px);
            box-shadow: 0 0 30px rgba(14,184,160,0.3);
        }

        /* Mobile nav hamburger */
        .nav-hamburger {
            display: none;
            background: none;
            border: none;
            color: var(--white);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
        }

        .nav-mobile {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(11,21,38,0.95);
            backdrop-filter: blur(20px);
            z-index: 99;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 32px;
        }

        .nav-mobile.open { display: flex; }
        .nav-mobile a {
            color: var(--white);
            text-decoration: none;
            font-family: var(--font-display);
            font-size: 1.4rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-mobile a:hover { color: var(--teal); }
        .nav-mobile .close-nav {
            position: absolute;
            top: 20px; right: 24px;
            background: none; border: none;
            color: var(--white);
            font-size: 1.8rem;
            cursor: pointer;
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
 
        .hero-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            max-width: 1300px;
            margin: 0 auto;
            width: 100%;
        }
 
        .hero-content {
            z-index: 1;
        }
 
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(14,184,160,0.1);
            border: 1px solid rgba(14,184,160,0.3);
            color: var(--teal);
            font-size: 0.78rem;
            font-weight: 500;
            padding: 7px 18px;
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
            font-family: var(--font-display);
            font-size: clamp(2.8rem, 5.5vw, 4.5rem);
            font-weight: 600;
            line-height: 1.08;
            margin-bottom: 16px;
            animation: fadeUp 0.8s 0.35s ease both;
        }
        .hero-title em {
            font-family: 'Instrument Serif', serif;
            font-style: italic;
            color: var(--teal);
        }
 
        .hero-subtitle {
            font-size: clamp(1rem, 1.8vw, 1.15rem);
            color: var(--muted);
            line-height: 1.7;
            margin-bottom: 40px;
            max-width: 480px;
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
            padding: 16px 34px;
            border-radius: 50px;
            transition: all 0.25s;
            box-shadow: 0 0 30px rgba(14,184,160,0.3);
            position: relative;
            overflow: hidden;
        }
        .btn-primary::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(180deg, rgba(255,255,255,0.1), transparent);
            opacity: 0;
            transition: opacity 0.2s;
        }
        .btn-primary:hover::after { opacity: 1; }
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
            padding: 16px 34px;
            border-radius: 50px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: all 0.25s;
        }
        .btn-secondary:hover {
            background: rgba(255,255,255,0.06);
            border-color: rgba(255,255,255,0.4);
            transform: translateY(-2px);
        }
 
        /* Hero image */
        .hero-visual {
            position: relative;
            animation: fadeUp 1s 0.4s ease both;
        }

        .hero-image {
            width: 100%;
            max-width: 520px;
            margin: 0 auto;
            border-radius: 32px;
            position: relative;
            z-index: 1;
        }

        .hero-image img {
            width: 100%;
            border-radius: 32px;
            filter: drop-shadow(0 20px 60px rgba(14,184,160,0.15));
        }

        .hero-float-card {
            position: absolute;
            background: rgba(15,30,54,0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 16px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 2;
            animation: float 4s ease-in-out infinite;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }

        .hero-float-card.card-1 {
            top: 10%;
            right: -10%;
            animation-delay: 0s;
        }

        .hero-float-card.card-2 {
            bottom: 15%;
            left: -8%;
            animation-delay: 1.5s;
        }

        .hero-float-card.card-3 {
            bottom: 5%;
            right: 5%;
            animation-delay: 3s;
        }

        .float-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .float-icon.teal { background: rgba(14,184,160,0.15); }
        .float-icon.accent { background: rgba(255,107,74,0.15); }
        .float-icon.blue { background: rgba(59,130,246,0.15); }

        .float-text { font-size: 0.8rem; }
        .float-text strong { display: block; font-size: 0.85rem; color: var(--white); }
        .float-text span { color: var(--muted); }
 
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to   { opacity: 1; transform: translateY(0); }
        }
 
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50%      { transform: translateY(-12px); }
        }
 
        /* ── TRUST BAR ── */
        .trust-bar {
            padding: 50px 60px;
            border-top: 1px solid rgba(255,255,255,0.06);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            background: rgba(255,255,255,0.015);
        }

        .trust-bar-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            gap: 60px;
            flex-wrap: wrap;
            align-items: center;
        }

        .trust-item {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .trust-icon {
            width: 48px; height: 48px;
            background: rgba(14,184,160,0.1);
            border: 1px solid rgba(14,184,160,0.2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .trust-text strong {
            display: block;
            font-family: var(--font-display);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--teal);
        }

        .trust-text span {
            font-size: 0.78rem;
            color: var(--muted);
            letter-spacing: 0.04em;
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
            font-family: var(--font-display);
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
            transition: all 0.35s var(--ease-out);
            position: relative;
            overflow: hidden;
        }
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--teal), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }
        .feature-card:hover {
            border-color: rgba(14,184,160,0.2);
            transform: translateY(-6px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }
        .feature-card:hover::before { opacity: 1; }
 
        .feature-card.featured {
            background: linear-gradient(135deg, rgba(14,184,160,0.12), rgba(14,184,160,0.04));
            border-color: rgba(14,184,160,0.2);
            grid-row: span 2;
        }
 
        .feature-icon {
            width: 52px; height: 52px;
            background: rgba(14,184,160,0.12);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 22px;
            transition: transform 0.3s var(--ease-out);
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(-3deg);
        }

        .feature-title {
            font-family: var(--font-display);
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
            font-family: var(--font-display);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--teal);
            margin: 0 auto 20px;
            position: relative;
            box-shadow: 0 0 24px rgba(14,184,160,0.2);
            transition: all 0.3s;
        }

        .step:hover .step-num {
            background: var(--teal);
            color: var(--navy);
            transform: scale(1.1);
            box-shadow: 0 0 40px rgba(14,184,160,0.35);
        }

        .step-title {
            font-family: var(--font-display);
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .step-desc {
            font-size: 0.8rem;
            color: var(--muted);
            line-height: 1.6;
        }
 
        /* ── TESTIMONIALS ── */
        .testimonials {
            padding: 100px 60px;
            background: rgba(255,255,255,0.015);
            border-top: 1px solid rgba(255,255,255,0.06);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }

        .testimonials-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-top: 60px;
        }

        .testimonial-card {
            background: var(--navy-mid);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px;
            transition: all 0.3s var(--ease-out);
            position: relative;
        }

        .testimonial-card::before {
            content: '"';
            font-family: 'Instrument Serif', serif;
            font-size: 4rem;
            color: var(--teal);
            opacity: 0.2;
            position: absolute;
            top: 16px;
            right: 28px;
            line-height: 1;
        }

        .testimonial-card:hover {
            border-color: rgba(14,184,160,0.2);
            transform: translateY(-4px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.2);
        }

        .testimonial-text {
            font-size: 0.9rem;
            color: var(--muted);
            line-height: 1.8;
            margin-bottom: 24px;
            font-style: italic;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .testimonial-avatar {
            width: 44px; height: 44px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--white);
        }

        .testimonial-name {
            font-weight: 600;
            font-size: 0.9rem;
        }

        .testimonial-role {
            font-size: 0.78rem;
            color: var(--muted);
        }

        .testimonial-stars {
            color: #F59E0B;
            font-size: 0.8rem;
            letter-spacing: 2px;
        }
 
        /* ── CTA ── */
        .cta-section {
            padding: 100px 60px 120px;
        }
        .cta-box {
            max-width: 960px;
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
            width: 500px; height: 500px;
            background: radial-gradient(circle, rgba(14,184,160,0.12), transparent 60%);
            pointer-events: none;
        }
        .cta-title {
            font-family: var(--font-display);
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
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }
        .cta-btns {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }
 
        /* ── FOOTER ── */
        footer {
            padding: 48px 60px;
            border-top: 1px solid rgba(255,255,255,0.06);
        }

        .footer-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
        }

        .footer-brand .footer-logo {
            font-family: var(--font-display);
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .footer-brand p {
            font-size: 0.85rem;
            color: var(--muted);
            line-height: 1.6;
            max-width: 280px;
        }

        .footer-col h4 {
            font-family: var(--font-display);
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--white);
        }

        .footer-col a {
            display: block;
            color: var(--muted);
            font-size: 0.85rem;
            margin-bottom: 10px;
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-col a:hover { color: var(--teal); }

        .footer-bottom {
            max-width: 1200px;
            margin: 32px auto 0;
            padding-top: 24px;
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
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
            .hero-grid { grid-template-columns: 1fr; text-align: center; }
            .hero-content { max-width: 640px; margin: 0 auto; }
            .hero-subtitle { margin-left: auto; margin-right: auto; }
            .hero-actions { justify-content: center; }
            .hero-visual { display: none; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .feature-card.featured { grid-row: span 1; }
            .steps { grid-template-columns: repeat(2, 1fr); gap: 40px; }
            .steps::before { display: none; }
            .testimonials-grid { grid-template-columns: repeat(2, 1fr); }
            nav { padding: 18px 30px; }
            .hero { padding: 140px 30px 80px; }
            .features, .how-it-works, .cta-section { padding-left: 30px; padding-right: 30px; }
            .trust-bar { padding: 40px 30px; }
            .testimonials { padding: 80px 30px; }
            .footer-inner { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 600px) {
            .features-grid { grid-template-columns: 1fr; }
            .steps { grid-template-columns: 1fr; }
            .testimonials-grid { grid-template-columns: 1fr; }
            .nav-links { display: none; }
            .nav-hamburger { display: block; }
            .trust-bar-inner { gap: 30px; }
            .trust-item { flex: 1 1 140px; }
            footer { padding: 40px 20px; }
            .footer-inner { grid-template-columns: 1fr; gap: 28px; }
            .cta-box { padding: 48px 24px; }
        }
    </style>
</head>
<body>
 
<div class="bg-gradient"></div>
 
<!-- NAV -->
<nav>
    <div style="display:flex; align-items:center;">
        <a href="index.php" class="logo">
            <div class="logo-dot"></div>
            MediConnect
        </a>
        <button id="themeToggle" style="background:none; border:none; color:var(--muted); cursor:pointer; font-size:1.2rem; display:flex; align-items:center;" title="Toggle Theme">🌓</button>
    </div>
    <ul class="nav-links">
        <li><a href="#features">Features</a></li>
        <li><a href="#how">How It Works</a></li>
        <li><a href="#testimonials">Reviews</a></li>
        <li><a href="login.php">Sign In</a></li>
        <li><a href="register.php" class="nav-cta">Get Started</a></li>
    </ul>
    <button class="nav-hamburger" onclick="document.querySelector('.nav-mobile').classList.add('open')">☰</button>
</nav>
 
<!-- Mobile Nav -->
<div class="nav-mobile" id="mobileNav">
    <button class="close-nav" onclick="this.parentElement.classList.remove('open')">✕</button>
    <a href="#features" onclick="this.parentElement.classList.remove('open')">Features</a>
    <a href="#how" onclick="this.parentElement.classList.remove('open')">How It Works</a>
    <a href="#testimonials" onclick="this.parentElement.classList.remove('open')">Reviews</a>
    <a href="login.php">Sign In</a>
    <a href="register.php" class="btn-primary" style="font-size:1rem;">Get Started →</a>
</div>

<!-- HERO -->
<section class="hero">
    <div class="hero-grid">
        <div class="hero-content">
            <div class="hero-badge">🩺 Smart Telemedicine Platform</div>
 
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
                    Book Appointment
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
                <a href="login.php" class="btn-secondary">
                    Start Consultation
                </a>
            </div>
        </div>

        <div class="hero-visual">
            <div class="hero-image">
                <img src="images/hero_doctor.png" alt="Doctor illustration - MediConnect telemedicine platform">
            </div>

            <div class="hero-float-card card-1">
                <div class="float-icon teal">📹</div>
                <div class="float-text">
                    <strong>HD Video Call</strong>
                    <span>Crystal clear quality</span>
                </div>
            </div>

            <div class="hero-float-card card-2">
                <div class="float-icon accent">💊</div>
                <div class="float-text">
                    <strong>E-Prescriptions</strong>
                    <span>Digital medicine tracking</span>
                </div>
            </div>

            <div class="hero-float-card card-3">
                <div class="float-icon blue">📊</div>
                <div class="float-text">
                    <strong>Health Monitoring</strong>
                </div>
            </div>
        </div>
    </div>
</section>
 
<!-- TRUST BAR -->
<div class="trust-bar reveal">
    <div class="trust-bar-inner">
        <div class="trust-item">
            <div class="trust-icon">🔒</div>
            <div class="trust-text">
                <strong>256-bit</strong>
                <span>SSL Encrypted</span>
            </div>
        </div>
        <div class="trust-item">
            <div class="trust-icon">🛡️</div>
            <div class="trust-text">
                <strong>HIPAA</strong>
                <span>Compliant</span>
            </div>
        </div>
        <div class="trust-item">
            <div class="trust-icon">🕐</div>
            <div class="trust-text">
                <strong>24/7</strong>
                <span>Doctor Availability</span>
            </div>
        </div>
        <div class="trust-item">
            <div class="trust-icon">⚡</div>
            <div class="trust-text">
                <strong><30s</strong>
                <span>Connection Time</span>
            </div>
        </div>
        <div class="trust-item">
            <div class="trust-icon">🎥</div>
            <div class="trust-text">
                <strong>HD</strong>
                <span>Video Quality</span>
            </div>
        </div>
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
 
<!-- TESTIMONIALS -->
<section class="testimonials" id="testimonials">
    <div class="testimonials-inner">
        <div class="reveal">
            <div class="section-label">What People Say</div>
            <h2 class="section-title">Trusted by <em>thousands</em> of patients</h2>
        </div>

        <div class="testimonials-grid">
            <div class="testimonial-card reveal">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">
                    "MediConnect made it so easy to see my doctor from home. The video quality is incredible, and the prescription tracking feature is a lifesaver!"
                </p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">S</div>
                    <div>
                        <div class="testimonial-name">Sarah Johnson</div>
                        <div class="testimonial-role">Patient · New York</div>
                    </div>
                </div>
            </div>

            <div class="testimonial-card reveal">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">
                    "As a doctor, this platform has transformed how I interact with my patients. The call alerts are instant, and managing appointments is seamless."
                </p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">D</div>
                    <div>
                        <div class="testimonial-name">Dr. Ahmed Khan</div>
                        <div class="testimonial-role">Cardiologist · London</div>
                    </div>
                </div>
            </div>

            <div class="testimonial-card reveal">
                <div class="testimonial-stars">★★★★★</div>
                <p class="testimonial-text">
                    "I can monitor my elderly mother's medicine intake remotely. The health monitoring feature gives me peace of mind every single day."
                </p>
                <div class="testimonial-author">
                    <div class="testimonial-avatar">M</div>
                    <div>
                        <div class="testimonial-name">Maria Chen</div>
                        <div class="testimonial-role">Caregiver · Singapore</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
 
<!-- CTA -->
<section class="cta-section">
    <div class="cta-box reveal">
        <h2 class="cta-title">Ready to experience <em>better healthcare?</em></h2>
        <p class="cta-sub">Join thousands of patients and doctors already using MediConnect for smarter, faster, and more connected care.</p>
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
    <div class="footer-inner">
        <div class="footer-brand">
            <div class="footer-logo">
                <div class="logo-dot" style="width:8px;height:8px;"></div>
                MediConnect
            </div>
            <p>Smart telemedicine & emergency assistance system. Connecting patients with healthcare professionals worldwide.</p>
        </div>
        <div class="footer-col">
            <h4>Platform</h4>
            <a href="#features">Features</a>
            <a href="#how">How It Works</a>
            <a href="#testimonials">Reviews</a>
        </div>
        <div class="footer-col">
            <h4>Get Started</h4>
            <a href="register.php?role=patient">Patient Signup</a>
            <a href="register.php?role=doctor">Doctor Signup</a>
            <a href="login.php">Sign In</a>
        </div>
        <div class="footer-col">
            <h4>Support</h4>
            <a href="#">Help Center</a>
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
        </div>
    </div>
    <div class="footer-bottom">
        <div>© 2026 MediConnect · Built with ❤️ for better healthcare</div>
        <div>Smart Telemedicine Platform</div>
    </div>
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
document.querySelectorAll('.feature-card, .step, .testimonial-card').forEach((el, i) => {
    el.style.transitionDelay = (i * 0.1) + 's';
});

// Navbar scroll effect
window.addEventListener('scroll', () => {
    const nav = document.querySelector('nav');
    if (window.scrollY > 50) {
        nav.style.background = 'rgba(11,21,38,0.92)';
        nav.style.borderBottomColor = 'rgba(255,255,255,0.1)';
    } else {
        nav.style.background = 'rgba(11,21,38,0.75)';
        nav.style.borderBottomColor = 'rgba(255,255,255,0.06)';
    }
});

// Theme Toggle
const themeToggle = document.getElementById('themeToggle');
if (localStorage.getItem('theme') === 'dark') {
    document.body.classList.add('dark-mode');
}
themeToggle.addEventListener('click', () => {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
});
</script>
 
</body>
</html>