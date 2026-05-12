<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MediConnect - Smart telemedicine platform. Connect with doctors instantly via HD video calls, manage prescriptions, and monitor your health.">
    <title>MediConnect - Smart Telemedicine Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Clash+Display:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f3efe7;
            --bg-soft: #fbf8f2;
            --surface: rgba(255, 255, 255, 0.78);
            --surface-strong: #fffdf9;
            --surface-dark: #10233e;
            --text: #122033;
            --muted: #617189;
            --muted-strong: #4e5d75;
            --line: rgba(18, 32, 51, 0.1);
            --teal: #0eb8a0;
            --teal-dark: #0a8a78;
            --coral: #ff7a59;
            --gold: #ffbf47;
            --sky: #5ea6ff;
            --shadow: 0 24px 80px rgba(17, 31, 52, 0.12);
            --shadow-soft: 0 16px 36px rgba(17, 31, 52, 0.08);
            --font-display: "Clash Display", sans-serif;
            --font-body: "DM Sans", sans-serif;
            --ease: cubic-bezier(0.16, 1, 0.3, 1);
        }

        body.dark-mode {
            --bg: #091324;
            --bg-soft: #0d1b30;
            --surface: rgba(14, 30, 54, 0.76);
            --surface-strong: #10233e;
            --surface-dark: #081424;
            --text: #f5f7fb;
            --muted: #8ea0bb;
            --muted-strong: #b0bed2;
            --line: rgba(255, 255, 255, 0.08);
            --shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            --shadow-soft: 0 16px 36px rgba(0, 0, 0, 0.24);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font-body);
            background:
                radial-gradient(circle at 12% 18%, rgba(14, 184, 160, 0.18), transparent 30%),
                radial-gradient(circle at 88% 20%, rgba(255, 122, 89, 0.12), transparent 28%),
                radial-gradient(circle at 72% 78%, rgba(94, 166, 255, 0.14), transparent 24%),
                linear-gradient(180deg, var(--bg-soft) 0%, var(--bg) 100%);
            color: var(--text);
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.22;
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 36px 36px;
            mask-image: radial-gradient(circle at center, black 35%, transparent 85%);
        }

        .page-shell {
            width: min(1380px, calc(100% - 40px));
            margin: 20px auto;
        }

        nav {
            position: sticky;
            top: 14px;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 16px 22px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 24px;
            backdrop-filter: blur(18px);
            box-shadow: var(--shadow-soft);
        }

        .brand-wrap {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .logo {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            color: var(--text);
            font-family: var(--font-display);
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -0.03em;
        }

        .logo-dot {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: linear-gradient(135deg, var(--teal), #55e6d2);
            box-shadow: 0 0 20px rgba(14, 184, 160, 0.45);
        }

        .theme-btn {
            border: 1px solid var(--line);
            background: transparent;
            color: var(--muted);
            border-radius: 14px;
            padding: 10px 12px;
            cursor: pointer;
            transition: 0.25s var(--ease);
        }

        .theme-btn:hover {
            background: rgba(14, 184, 160, 0.08);
            color: var(--text);
        }

        .nav-links {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--muted);
            font-size: 0.92rem;
            font-weight: 600;
            padding: 10px 12px;
            border-radius: 14px;
            transition: 0.25s var(--ease);
        }

        .nav-links a:hover {
            color: var(--text);
            background: rgba(14, 184, 160, 0.08);
        }

        .nav-cta {
            background: linear-gradient(135deg, var(--teal), #38d4bf);
            color: #07241f !important;
            box-shadow: 0 12px 28px rgba(14, 184, 160, 0.25);
        }

        .nav-cta:hover {
            transform: translateY(-1px);
            background: linear-gradient(135deg, var(--teal-dark), var(--teal));
            color: #ffffff !important;
        }

        .nav-hamburger {
            display: none;
            border: none;
            background: transparent;
            color: var(--text);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .nav-mobile {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 120;
            background: rgba(8, 20, 36, 0.92);
            backdrop-filter: blur(16px);
            padding: 30px;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 24px;
        }

        .nav-mobile.open { display: flex; }
        .nav-mobile a {
            color: #ffffff;
            text-decoration: none;
            font-family: var(--font-display);
            font-size: 1.3rem;
        }

        .close-nav {
            position: absolute;
            top: 24px;
            right: 24px;
            border: none;
            background: transparent;
            color: #ffffff;
            font-size: 1.9rem;
            cursor: pointer;
        }

        .hero {
            padding: 42px 0 22px;
        }

        .hero-panel {
            background:
                radial-gradient(circle at top left, rgba(14, 184, 160, 0.14), transparent 28%),
                radial-gradient(circle at bottom right, rgba(255, 122, 89, 0.12), transparent 24%),
                var(--surface);
            border: 1px solid var(--line);
            border-radius: 40px;
            box-shadow: var(--shadow);
            padding: 34px;
            backdrop-filter: blur(20px);
            overflow: hidden;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.15fr 0.85fr;
            gap: 28px;
            align-items: stretch;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 9px 14px;
            border-radius: 999px;
            background: rgba(14, 184, 160, 0.1);
            border: 1px solid rgba(14, 184, 160, 0.22);
            color: var(--teal-dark);
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 22px;
        }

        .eyebrow::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--teal);
            box-shadow: 0 0 12px rgba(14, 184, 160, 0.45);
        }

        .hero-title {
            font-family: var(--font-display);
            font-size: clamp(3rem, 6vw, 5.7rem);
            line-height: 0.98;
            letter-spacing: -0.06em;
            max-width: 9.5ch;
            margin-bottom: 20px;
        }

        .hero-title em {
            font-family: "Instrument Serif", serif;
            font-style: italic;
            font-weight: 400;
            color: var(--coral);
        }

        .hero-copy {
            max-width: 560px;
            color: var(--muted-strong);
            font-size: 1.06rem;
            line-height: 1.8;
            margin-bottom: 28px;
        }

        .hero-actions {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .btn-primary,
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            border-radius: 999px;
            padding: 15px 24px;
            font-weight: 700;
            font-size: 0.95rem;
            transition: 0.28s var(--ease);
        }

        .btn-primary {
            color: #07241f;
            background: linear-gradient(135deg, var(--teal), #48dbc7);
            box-shadow: 0 16px 32px rgba(14, 184, 160, 0.22);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            color: #ffffff;
            background: linear-gradient(135deg, var(--teal-dark), var(--teal));
        }

        .btn-secondary {
            color: var(--text);
            border: 1px solid var(--line);
            background: rgba(255,255,255,0.3);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            border-color: rgba(14, 184, 160, 0.24);
            background: rgba(14, 184, 160, 0.08);
        }

        .hero-meta {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .metric-card {
            background: rgba(255,255,255,0.42);
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 18px;
            min-height: 122px;
        }

        .metric-value {
            font-family: var(--font-display);
            font-size: 2rem;
            line-height: 1;
            margin-bottom: 10px;
        }

        .metric-label {
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 8px;
        }

        .metric-note {
            color: var(--muted-strong);
            font-size: 0.88rem;
            line-height: 1.55;
        }

        .hero-side {
            display: grid;
            grid-template-rows: 1.1fr auto;
            gap: 16px;
        }

        .hero-visual {
            position: relative;
            min-height: 500px;
            background:
                linear-gradient(180deg, rgba(14, 184, 160, 0.16), rgba(94, 166, 255, 0.04)),
                rgba(255,255,255,0.36);
            border: 1px solid var(--line);
            border-radius: 34px;
            padding: 26px;
            overflow: hidden;
        }

        .visual-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(16, 35, 62, 0.86);
            color: #dffaf5;
            font-size: 0.76rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .visual-label::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 999px;
            background: #48dbc7;
        }

        .doctor-stage {
            position: absolute;
            inset: 74px 26px 26px 26px;
            display: flex;
            align-items: flex-start;
            border-radius: 28px;
            background:
                linear-gradient(180deg, rgba(16, 35, 62, 0.95), rgba(8, 20, 36, 0.98));
            overflow: hidden;
            padding: 24px;
        }

        .doctor-stage::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 76% 34%, rgba(94, 166, 255, 0.12), transparent 22%),
                linear-gradient(90deg, rgba(5, 16, 28, 0.92) 0%, rgba(5, 16, 28, 0.84) 34%, rgba(5, 16, 28, 0.4) 60%, rgba(5, 16, 28, 0.05) 80%);
            z-index: 0;
        }

        .stage-insights {
            position: relative;
            z-index: 2;
            width: min(37%, 210px);
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding-top: 12px;
        }

        .doctor-stage img {
            position: absolute;
            right: 8px;
            bottom: 0;
            width: min(58%, 340px);
            filter: drop-shadow(0 30px 60px rgba(0, 0, 0, 0.35));
            z-index: 1;
        }

        .stage-card {
            position: relative;
            max-width: none;
            background: linear-gradient(180deg, rgba(255,255,255,0.12), rgba(255,255,255,0.06));
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 22px;
            backdrop-filter: blur(16px);
            padding: 16px 16px 15px;
            color: #eaf6ff;
            box-shadow: 0 18px 40px rgba(0,0,0,0.2);
            animation: floatY 5s ease-in-out infinite;
            z-index: 3;
        }

        .stage-card strong {
            display: block;
            font-family: var(--font-display);
            font-size: 0.95rem;
            margin-bottom: 8px;
        }

        .stage-card span {
            font-size: 0.82rem;
            line-height: 1.6;
            color: #aec4d9;
        }

        .stage-card.one { animation-delay: 0.2s; }
        .stage-card.two { animation-delay: 1.5s; }
        .stage-card.three {
            position: absolute;
            top: 34px;
            right: 26px;
            width: min(44%, 248px);
            animation-delay: 2.7s;
            background: linear-gradient(180deg, rgba(14, 184, 160, 0.16), rgba(255,255,255,0.08));
        }

        .hero-mini-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .mini-panel {
            background: var(--surface-strong);
            border: 1px solid var(--line);
            border-radius: 24px;
            padding: 18px;
        }

        .mini-panel-title {
            font-size: 0.76rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 10px;
        }

        .mini-panel strong {
            display: block;
            font-family: var(--font-display);
            font-size: 1.02rem;
            margin-bottom: 8px;
        }

        .mini-panel p {
            color: var(--muted-strong);
            font-size: 0.84rem;
            line-height: 1.55;
        }

        @keyframes floatY {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .section-shell {
            padding: 92px 0 0;
        }

        .section-intro {
            display: flex;
            justify-content: space-between;
            align-items: end;
            gap: 18px;
            margin-bottom: 34px;
        }

        .section-kicker {
            color: var(--teal-dark);
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 14px;
        }

        .section-title {
            font-family: var(--font-display);
            font-size: clamp(2rem, 4vw, 3.35rem);
            line-height: 1.02;
            letter-spacing: -0.05em;
            max-width: 12ch;
        }

        .section-title em {
            font-family: "Instrument Serif", serif;
            font-style: italic;
            font-weight: 400;
            color: var(--teal);
        }

        .section-copy {
            max-width: 420px;
            color: var(--muted-strong);
            line-height: 1.8;
        }

        .trust-band {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
        }

        .trust-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 26px;
            padding: 22px;
            box-shadow: var(--shadow-soft);
        }

        .trust-card-icon {
            width: 52px;
            height: 52px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 16px;
            margin-bottom: 16px;
            font-size: 1.2rem;
            background: rgba(14, 184, 160, 0.12);
            color: var(--teal-dark);
        }

        .trust-card strong {
            display: block;
            font-family: var(--font-display);
            font-size: 1.25rem;
            margin-bottom: 6px;
        }

        .trust-card span {
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .features-mosaic {
            display: grid;
            grid-template-columns: 1.25fr 1fr 1fr;
            gap: 18px;
        }

        .feature-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 30px;
            padding: 28px;
            min-height: 240px;
            box-shadow: var(--shadow-soft);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s var(--ease), box-shadow 0.3s var(--ease), border-color 0.3s var(--ease);
        }

        .feature-card:hover {
            transform: translateY(-6px);
            border-color: rgba(14, 184, 160, 0.22);
            box-shadow: var(--shadow);
        }

        .feature-card.featured {
            grid-row: span 2;
            background:
                radial-gradient(circle at top left, rgba(14, 184, 160, 0.14), transparent 34%),
                linear-gradient(180deg, rgba(16, 35, 62, 0.97), rgba(9, 19, 36, 0.98));
            color: #f7fbff;
        }

        .feature-card.featured .feature-desc,
        .feature-card.featured .feature-list li {
            color: #b4c8db;
        }

        .feature-icon {
            width: 58px;
            height: 58px;
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.45rem;
            background: rgba(14, 184, 160, 0.12);
            color: var(--teal-dark);
            margin-bottom: 22px;
        }

        .feature-card.featured .feature-icon {
            background: rgba(255,255,255,0.08);
            color: #6ae8d7;
        }

        .feature-title {
            font-family: var(--font-display);
            font-size: 1.18rem;
            margin-bottom: 12px;
        }

        .feature-desc {
            color: var(--muted-strong);
            font-size: 0.92rem;
            line-height: 1.8;
        }

        .feature-list {
            list-style: none;
            margin-top: 18px;
            display: grid;
            gap: 10px;
        }

        .feature-list li {
            color: var(--muted-strong);
            font-size: 0.86rem;
            display: flex;
            gap: 10px;
            align-items: start;
        }

        .feature-list li::before {
            content: "";
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--teal);
            margin-top: 7px;
            flex: 0 0 auto;
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
        }

        .step-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 26px;
            box-shadow: var(--shadow-soft);
            position: relative;
        }

        .step-index {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--teal), var(--teal-dark));
            color: #ffffff;
            font-family: var(--font-display);
            font-size: 1rem;
            margin-bottom: 18px;
        }

        .step-title {
            font-family: var(--font-display);
            font-size: 1.06rem;
            margin-bottom: 10px;
        }

        .step-desc {
            color: var(--muted-strong);
            font-size: 0.9rem;
            line-height: 1.75;
        }

        .reviews-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .review-card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: 28px;
            box-shadow: var(--shadow-soft);
            position: relative;
        }

        .review-stars {
            color: var(--gold);
            letter-spacing: 0.16em;
            font-size: 0.82rem;
            margin-bottom: 18px;
        }

        .review-text {
            color: var(--muted-strong);
            line-height: 1.9;
            font-size: 0.94rem;
            margin-bottom: 24px;
        }

        .review-author {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .review-avatar {
            width: 46px;
            height: 46px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--teal), var(--sky));
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
        }

        .review-name {
            font-weight: 700;
            margin-bottom: 4px;
        }

        .review-role {
            color: var(--muted);
            font-size: 0.82rem;
        }

        .cta-shell {
            padding: 92px 0;
        }

        .cta-panel {
            background:
                radial-gradient(circle at top center, rgba(14, 184, 160, 0.16), transparent 28%),
                linear-gradient(180deg, rgba(16, 35, 62, 0.98), rgba(9, 19, 36, 1));
            border: 1px solid rgba(14, 184, 160, 0.18);
            border-radius: 40px;
            box-shadow: var(--shadow);
            padding: 66px 56px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 24px;
            align-items: center;
            color: #f6fbff;
        }

        .cta-panel .section-kicker { color: #6ae8d7; }
        .cta-panel .section-title { max-width: 11ch; }
        .cta-panel .section-title em { color: var(--coral); }
        .cta-panel .section-copy { color: #a9bfd3; }

        .cta-stack {
            display: grid;
            gap: 14px;
        }

        .cta-note {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            padding: 18px;
            color: #c8d8e8;
            font-size: 0.9rem;
            line-height: 1.7;
        }

        footer {
            padding: 0 0 26px;
        }

        .footer-shell {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 34px;
            box-shadow: var(--shadow-soft);
            padding: 30px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.6fr 1fr 1fr 1fr;
            gap: 28px;
        }

        .footer-brand h3 {
            font-family: var(--font-display);
            font-size: 1.2rem;
            margin-bottom: 12px;
        }

        .footer-brand p {
            max-width: 300px;
            color: var(--muted-strong);
            line-height: 1.8;
            font-size: 0.9rem;
        }

        .footer-col h4 {
            font-family: var(--font-display);
            font-size: 0.88rem;
            margin-bottom: 14px;
        }

        .footer-col a {
            display: block;
            color: var(--muted);
            text-decoration: none;
            margin-bottom: 10px;
            font-size: 0.88rem;
            transition: 0.2s;
        }

        .footer-col a:hover { color: var(--teal-dark); }

        .footer-bottom {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            color: var(--muted);
            font-size: 0.82rem;
            flex-wrap: wrap;
        }

        .reveal {
            opacity: 0;
            transform: translateY(26px);
            transition: opacity 0.75s ease, transform 0.75s ease;
        }

        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 1180px) {
            .hero-grid,
            .cta-panel {
                grid-template-columns: 1fr;
            }

            .hero-side {
                grid-template-rows: auto;
            }

            .trust-band,
            .features-mosaic,
            .process-grid,
            .reviews-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .feature-card.featured {
                grid-row: span 1;
                grid-column: span 2;
            }

            .footer-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 760px) {
            .page-shell {
                width: min(100% - 20px, 1380px);
                margin: 10px auto;
            }

            nav {
                padding: 14px 16px;
            }

            .nav-links { display: none; }
            .nav-hamburger { display: inline-flex; }

            .hero-panel,
            .cta-panel,
            .footer-shell {
                padding: 24px;
                border-radius: 28px;
            }

            .hero-visual {
                min-height: 380px;
            }

            .stage-insights {
                width: min(54%, 220px);
                gap: 10px;
                padding-top: 0;
            }

            .doctor-stage img {
                right: -8px;
                width: min(70%, 300px);
            }

            .stage-card {
                padding: 13px 13px 12px;
                border-radius: 18px;
            }

            .stage-card.three {
                top: 18px;
                right: 14px;
                width: min(42%, 160px);
            }

            .stage-card strong {
                font-size: 0.88rem;
                margin-bottom: 6px;
            }

            .stage-card span {
                font-size: 0.74rem;
                line-height: 1.45;
            }

            .hero-meta,
            .hero-mini-grid,
            .trust-band,
            .features-mosaic,
            .process-grid,
            .reviews-grid,
            .footer-grid {
                grid-template-columns: 1fr;
            }

            .feature-card.featured {
                grid-column: span 1;
            }

            .section-intro {
                flex-direction: column;
                align-items: start;
            }

            .hero-title,
            .section-title {
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <nav>
            <div class="brand-wrap">
                <a href="index.php" class="logo">
                    <span class="logo-dot"></span>
                    <span>MediConnect</span>
                </a>
                <button id="themeToggle" class="theme-btn" title="Toggle Theme">Theme</button>
            </div>

            <ul class="nav-links">
                <li><a href="#features">Features</a></li>
                <li><a href="#how">How It Works</a></li>
                <li><a href="#testimonials">Reviews</a></li>
                <li><a href="login.php">Sign In</a></li>
                <li><a href="admin_login.php">Admin</a></li>
                <li><a href="register.php" class="nav-cta">Get Started</a></li>
            </ul>

            <button class="nav-hamburger" onclick="document.getElementById('mobileNav').classList.add('open')">+</button>
        </nav>

        <div class="nav-mobile" id="mobileNav">
            <button class="close-nav" onclick="document.getElementById('mobileNav').classList.remove('open')">x</button>
            <a href="#features" onclick="document.getElementById('mobileNav').classList.remove('open')">Features</a>
            <a href="#how" onclick="document.getElementById('mobileNav').classList.remove('open')">How It Works</a>
            <a href="#testimonials" onclick="document.getElementById('mobileNav').classList.remove('open')">Reviews</a>
            <a href="login.php">Sign In</a>
            <a href="admin_login.php">Admin</a>
            <a href="register.php">Get Started</a>
        </div>

        <section class="hero">
            <div class="hero-panel">
                <div class="hero-grid">
                    <div class="hero-main">
                        <div class="eyebrow reveal">Smart Telemedicine Platform</div>
                        <h1 class="hero-title reveal">Clinical care, <em>recomposed</em> for modern life.</h1>
                        <p class="hero-copy reveal">
                            MediConnect brings appointments, live consultation, prescriptions, health tracking, caregiver monitoring, and admin-reviewed doctor onboarding into one focused care experience.
                        </p>

                        <div class="hero-actions reveal">
                            <a href="register.php" class="btn-primary">Book Appointment</a>
                            <a href="login.php" class="btn-secondary">Start Consultation</a>
                            <a href="admin_login.php" class="btn-secondary">Admin Access</a>
                        </div>

                        <div class="hero-meta">
                            <div class="metric-card reveal">
                                <div class="metric-label">Connection Time</div>
                                <div class="metric-value">&lt;30s</div>
                                <div class="metric-note">Patients can move from dashboard to live doctor consultation in seconds.</div>
                            </div>
                            <div class="metric-card reveal">
                                <div class="metric-label">Availability</div>
                                <div class="metric-value">24/7</div>
                                <div class="metric-note">Support urgent check-ins, scheduled calls, and follow-up reviews from anywhere.</div>
                            </div>
                            <div class="metric-card reveal">
                                <div class="metric-label">Care Loop</div>
                                <div class="metric-value">All-in-1</div>
                                <div class="metric-note">Booking, calls, medicines, reports, and monitoring stay in one patient journey.</div>
                            </div>
                        </div>
                    </div>

                    <div class="hero-side">
                        <div class="hero-visual reveal">
                            <div class="visual-label">Live Consultation Layer</div>
                            <div class="doctor-stage">
                                <div class="stage-insights">
                                    <div class="stage-card one">
                                        <strong>HD consultation room</strong>
                                        <span>Join crystal-clear video sessions without extra apps or complicated setup.</span>
                                    </div>
                                    <div class="stage-card two">
                                        <strong>Prescription follow-through</strong>
                                        <span>Doctors can prescribe, and patients can track intake from the same system.</span>
                                    </div>
                                </div>
                                <div class="stage-card three">
                                    <strong>Verified clinicians</strong>
                                    <span>Doctor visibility can be gated by admin credential approval for trust-first booking.</span>
                                </div>
                                <img src="images/hero_doctor.png" alt="Doctor illustration - MediConnect telemedicine platform">
                            </div>
                        </div>

                        <div class="hero-mini-grid">
                            <div class="mini-panel reveal">
                                <div class="mini-panel-title">Consult</div>
                                <strong>Video first</strong>
                                <p>Fast browser-based calls built for telemedicine, not generic meetings.</p>
                            </div>
                            <div class="mini-panel reveal">
                                <div class="mini-panel-title">Track</div>
                                <strong>Medicine routines</strong>
                                <p>Daily intake, checklists, and logged vitals stay visible over time.</p>
                            </div>
                            <div class="mini-panel reveal">
                                <div class="mini-panel-title">Share</div>
                                <strong>Reports and access</strong>
                                <p>Upload documents securely and control who can view them.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section-shell reveal">
            <div class="trust-band">
                <div class="trust-card">
                    <div class="trust-card-icon">Lock</div>
                    <strong>256-bit</strong>
                    <span>SSL encrypted</span>
                </div>
                <div class="trust-card">
                    <div class="trust-card-icon">Shield</div>
                    <strong>HIPAA</strong>
                    <span>Compliance ready</span>
                </div>
                <div class="trust-card">
                    <div class="trust-card-icon">Clock</div>
                    <strong>24/7</strong>
                    <span>Doctor availability</span>
                </div>
                <div class="trust-card">
                    <div class="trust-card-icon">Video</div>
                    <strong>HD</strong>
                    <span>Video quality</span>
                </div>
                <div class="trust-card">
                    <div class="trust-card-icon">Pulse</div>
                    <strong>Live</strong>
                    <span>Health monitoring</span>
                </div>
            </div>
        </section>

        <section class="section-shell" id="features">
            <div class="section-intro reveal">
                <div>
                    <div class="section-kicker">What We Offer</div>
                    <h2 class="section-title">Everything needed for <em>continuous care</em></h2>
                </div>
                <p class="section-copy">
                    This is not just a call screen. It is a connected care workspace for patients, doctors, caregivers, and administrators.
                </p>
            </div>

            <div class="features-mosaic">
                <article class="feature-card featured reveal">
                    <div class="feature-icon">Video</div>
                    <h3 class="feature-title">High-definition consultations with clinical follow-through</h3>
                    <p class="feature-desc">
                        Start browser-based doctor visits with fast call alerts, consultation continuity, and workflows built specifically for healthcare interactions.
                    </p>
                    <ul class="feature-list">
                        <li>Instant call prompts for both patients and doctors</li>
                        <li>Appointment-linked live consultation flow</li>
                        <li>Safer onboarding with admin-controlled doctor approval</li>
                    </ul>
                </article>

                <article class="feature-card reveal">
                    <div class="feature-icon">Rx</div>
                    <h3 class="feature-title">Medicine Checklist</h3>
                    <p class="feature-desc">Track routines, dosages, and confirmations so patients can stay on schedule day after day.</p>
                </article>

                <article class="feature-card reveal">
                    <div class="feature-icon">Cal</div>
                    <h3 class="feature-title">Appointment Booking</h3>
                    <p class="feature-desc">Browse verified doctors, choose specializations, and lock in time slots with a guided flow.</p>
                </article>

                <article class="feature-card reveal">
                    <div class="feature-icon">Care</div>
                    <h3 class="feature-title">Patient Monitoring</h3>
                    <p class="feature-desc">Give trusted caregivers visibility into medicine adherence and health activity when support matters.</p>
                </article>

                <article class="feature-card reveal">
                    <div class="feature-icon">AI</div>
                    <h3 class="feature-title">AI Health Assistant</h3>
                    <p class="feature-desc">Offer always-on answers for everyday questions while keeping the main care path human-led.</p>
                </article>

                <article class="feature-card reveal">
                    <div class="feature-icon">Docs</div>
                    <h3 class="feature-title">Report Uploads</h3>
                    <p class="feature-desc">Share labs and medical files securely before, during, or after a consultation.</p>
                </article>
            </div>
        </section>

        <section class="section-shell" id="how">
            <div class="section-intro reveal">
                <div>
                    <div class="section-kicker">The Process</div>
                    <h2 class="section-title">From signup to <em>care delivered</em></h2>
                </div>
                <p class="section-copy">
                    The flow is simple for first-time users, but strong enough to support booking, verification, calling, and follow-up in one place.
                </p>
            </div>

            <div class="process-grid">
                <article class="step-card reveal">
                    <div class="step-index">01</div>
                    <h3 class="step-title">Create account</h3>
                    <p class="step-desc">Register as patient or doctor with a clean, guided onboarding flow.</p>
                </article>
                <article class="step-card reveal">
                    <div class="step-index">02</div>
                    <h3 class="step-title">Choose specialty and clinician</h3>
                    <p class="step-desc">Patients book against verified doctors and available appointment windows.</p>
                </article>
                <article class="step-card reveal">
                    <div class="step-index">03</div>
                    <h3 class="step-title">Join live consultation</h3>
                    <p class="step-desc">Call workflows, ringing logic, and readiness notifications guide both sides.</p>
                </article>
                <article class="step-card reveal">
                    <div class="step-index">04</div>
                    <h3 class="step-title">Continue care</h3>
                    <p class="step-desc">Manage prescriptions, vitals, uploaded reports, and caregiver visibility afterward.</p>
                </article>
            </div>
        </section>

        <section class="section-shell" id="testimonials">
            <div class="section-intro reveal">
                <div>
                    <div class="section-kicker">What People Say</div>
                    <h2 class="section-title">Trusted by <em>patients and clinicians</em></h2>
                </div>
                <p class="section-copy">
                    Designed to feel calm for patients and efficient for medical professionals who need fewer steps and clearer context.
                </p>
            </div>

            <div class="reviews-grid">
                <article class="review-card reveal">
                    <div class="review-stars">*****</div>
                    <p class="review-text">"MediConnect made it easy to meet my doctor from home. The video quality feels polished, and medicine tracking finally keeps me consistent."</p>
                    <div class="review-author">
                        <div class="review-avatar">S</div>
                        <div>
                            <div class="review-name">Sarah Johnson</div>
                            <div class="review-role">Patient - New York</div>
                        </div>
                    </div>
                </article>

                <article class="review-card reveal">
                    <div class="review-stars">*****</div>
                    <p class="review-text">"The experience feels purpose-built for healthcare. I can see appointments, connect quickly, and keep my patient workflow focused."</p>
                    <div class="review-author">
                        <div class="review-avatar">D</div>
                        <div>
                            <div class="review-name">Dr. Ahmed Khan</div>
                            <div class="review-role">Cardiologist - London</div>
                        </div>
                    </div>
                </article>

                <article class="review-card reveal">
                    <div class="review-stars">*****</div>
                    <p class="review-text">"The caregiver monitoring flow gives me peace of mind. I can follow along without constantly interrupting my mother's day."</p>
                    <div class="review-author">
                        <div class="review-avatar">M</div>
                        <div>
                            <div class="review-name">Maria Chen</div>
                            <div class="review-role">Caregiver - Singapore</div>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="cta-shell">
            <div class="cta-panel reveal">
                <div>
                    <div class="section-kicker">Get Started</div>
                    <h2 class="section-title">Build a <em>better care loop</em> from the first visit.</h2>
                    <p class="section-copy">Whether you are booking as a patient or joining as a doctor, MediConnect is designed to make digital care feel direct, trustworthy, and human.</p>
                </div>
                <div class="cta-stack">
                    <a href="register.php?role=patient" class="btn-primary">I'm a Patient</a>
                    <a href="register.php?role=doctor" class="btn-secondary">I'm a Doctor</a>
                    <div class="cta-note">Admin reviewers can verify doctor credentials before doctors appear in patient appointment booking. That keeps the public experience trust-led without adding complexity to the main flow.</div>
                </div>
            </div>
        </section>

        <footer>
            <div class="footer-shell">
                <div class="footer-grid">
                    <div class="footer-brand">
                        <h3>MediConnect</h3>
                        <p>Smart telemedicine and care coordination system for video consultation, prescriptions, monitoring, and report sharing.</p>
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
                        <a href="admin_login.php">Admin Login</a>
                    </div>
                    <div class="footer-col">
                        <h4>Support</h4>
                        <a href="#">Help Center</a>
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                    </div>
                </div>
                <div class="footer-bottom">
                    <div>&copy; 2026 MediConnect - Built for better healthcare.</div>
                    <div>Smart Telemedicine Platform</div>
                </div>
            </div>
        </footer>
    </div>

    <script>
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12 });

    document.querySelectorAll('.reveal').forEach((element, index) => {
        element.style.transitionDelay = `${index * 0.05}s`;
        observer.observe(element);
    });

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
