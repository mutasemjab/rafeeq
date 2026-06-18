@php
    $copy = trans('landing');
    $nav = $copy['navigation'];
    $hero = $copy['hero'];
    $services = $copy['services'];
    $platform = $copy['platform'];
    $admin = $copy['admin'];
    $privacy = $copy['privacy'];
    $cta = $copy['cta'];
    $footer = $copy['footer'];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $copy['meta']['title'] }}</title>
    <meta name="description" content="{{ $copy['meta']['description'] }}">
    <meta property="og:title" content="{{ $copy['meta']['title'] }}">
    <meta property="og:description" content="{{ $copy['meta']['description'] }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('assets/front/rafiq-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #06131c;
            --bg-soft: #0b2230;
            --panel: rgba(7, 27, 40, 0.74);
            --panel-strong: rgba(10, 34, 49, 0.92);
            --line: rgba(153, 230, 255, 0.16);
            --line-strong: rgba(153, 230, 255, 0.28);
            --text: #ecf9ff;
            --muted: #9ac9d8;
            --accent: #59e3ff;
            --accent-strong: #11b9da;
            --accent-soft: rgba(89, 227, 255, 0.14);
            --warm: #9ff7db;
            --shadow: 0 24px 90px rgba(0, 0, 0, 0.32);
            --radius-xl: 32px;
            --radius-lg: 24px;
            --radius-md: 18px;
            --site-width: 1180px;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(64, 191, 224, 0.20), transparent 28%),
                radial-gradient(circle at 84% 18%, rgba(74, 247, 201, 0.16), transparent 24%),
                radial-gradient(circle at 50% 100%, rgba(17, 185, 218, 0.18), transparent 34%),
                linear-gradient(160deg, #031018 0%, #06131c 40%, #092232 100%);
            font-family: 'Plus Jakarta Sans', sans-serif;
            position: relative;
            overflow-x: hidden;
        }

        html[lang="ar"] body,
        body.rtl {
            font-family: 'IBM Plex Sans Arabic', sans-serif;
        }

        body::before,
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 0;
        }

        body::before {
            background-image:
                linear-gradient(rgba(143, 221, 255, 0.06) 1px, transparent 1px),
                linear-gradient(90deg, rgba(143, 221, 255, 0.06) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: linear-gradient(to bottom, rgba(0, 0, 0, 0.22), transparent 75%);
        }

        body::after {
            background:
                radial-gradient(circle at 20% 18%, rgba(89, 227, 255, 0.18), transparent 22%),
                radial-gradient(circle at 82% 12%, rgba(159, 247, 219, 0.18), transparent 20%),
                radial-gradient(circle at 52% 68%, rgba(7, 54, 76, 0.62), transparent 32%);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        img {
            display: block;
            max-width: 100%;
        }

        .page-shell {
            position: relative;
            z-index: 1;
            width: min(var(--site-width), calc(100% - 32px));
            margin: 0 auto;
            padding: 24px 0 64px;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 16px 18px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: rgba(5, 20, 30, 0.70);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
            position: sticky;
            top: 18px;
            z-index: 30;
            transition: border-color 0.24s ease, box-shadow 0.24s ease, background 0.24s ease;
        }

        .topbar.is-scrolled {
            border-color: var(--line-strong);
            background: rgba(5, 20, 30, 0.82);
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.16);
        }

        .menu-toggle {
            display: none;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border: 1px solid rgba(153, 230, 255, 0.16);
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.03);
            color: #fff;
            font: inherit;
            font-size: 0.92rem;
            font-weight: 700;
            cursor: pointer;
        }

        .menu-toggle-icon {
            display: inline-grid;
            gap: 4px;
        }

        .menu-toggle-icon span {
            display: block;
            width: 18px;
            height: 2px;
            border-radius: 999px;
            background: currentColor;
            transform-origin: center;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .menu-toggle.is-open .menu-toggle-icon span:nth-child(1) {
            transform: translateY(6px) rotate(45deg);
        }

        .menu-toggle.is-open .menu-toggle-icon span:nth-child(2) {
            opacity: 0;
        }

        .menu-toggle.is-open .menu-toggle-icon span:nth-child(3) {
            transform: translateY(-6px) rotate(-45deg);
        }

        .brand-lockup {
            display: inline-flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .brand-mark {
            width: 58px;
            height: 58px;
            padding: 8px;
            border-radius: 18px;
            background:
                linear-gradient(160deg, rgba(89, 227, 255, 0.20), rgba(17, 185, 218, 0.06)),
                rgba(7, 27, 40, 0.66);
            border: 1px solid rgba(153, 230, 255, 0.18);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.06);
        }

        .brand-copy {
            min-width: 0;
        }

        .brand-name {
            margin: 0;
            font-size: 1rem;
            font-family: 'Space Grotesk', 'Plus Jakarta Sans', sans-serif;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        html[lang="ar"] .brand-name {
            font-family: 'IBM Plex Sans Arabic', sans-serif;
        }

        .brand-tagline {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 0.88rem;
        }

        .nav-cluster {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nav-links a {
            padding: 10px 14px;
            border-radius: 999px;
            color: rgba(236, 249, 255, 0.88);
            font-size: 0.92rem;
            transition: background 0.2s ease, color 0.2s ease, transform 0.2s ease;
        }

        .nav-links a:hover {
            background: rgba(89, 227, 255, 0.10);
            color: #fff;
            transform: translateY(-1px);
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .locale-switcher {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px;
            border-radius: 999px;
            background: rgba(89, 227, 255, 0.08);
            border: 1px solid rgba(89, 227, 255, 0.10);
        }

        .locale-switcher a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 13px;
            border-radius: 999px;
            font-size: 0.84rem;
            color: var(--muted);
            transition: background 0.2s ease, color 0.2s ease;
        }

        .locale-switcher a.active {
            background: linear-gradient(135deg, rgba(89, 227, 255, 0.22), rgba(17, 185, 218, 0.22));
            color: #fff;
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 0.95rem;
            font-weight: 700;
            transition: transform 0.24s ease, box-shadow 0.24s ease, background 0.24s ease, border-color 0.24s ease;
            cursor: pointer;
        }

        .button:hover {
            transform: translateY(-2px);
        }

        .button-primary {
            color: #032432;
            background: linear-gradient(135deg, var(--warm), var(--accent));
            box-shadow: 0 18px 38px rgba(17, 185, 218, 0.26);
        }

        .button-secondary {
            background: rgba(255, 255, 255, 0.03);
            border-color: rgba(153, 230, 255, 0.16);
            color: #fff;
        }

        .status-banner {
            margin: 18px 0 0;
            padding: 14px 18px;
            border-radius: 18px;
            border: 1px solid rgba(159, 247, 219, 0.24);
            background: rgba(159, 247, 219, 0.10);
            color: #dffef5;
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
            gap: 42px;
            align-items: center;
            padding: 48px 0 24px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(89, 227, 255, 0.08);
            color: var(--warm);
            font-size: 0.84rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        html[lang="ar"] .eyebrow {
            letter-spacing: 0.02em;
        }

        .hero-copy h1,
        .section-head h2,
        .cta-panel h2 {
            margin: 18px 0 0;
            font-size: clamp(2.7rem, 4vw, 5rem);
            line-height: 0.97;
            letter-spacing: -0.04em;
            font-family: 'Space Grotesk', 'Plus Jakarta Sans', sans-serif;
        }

        html[lang="ar"] .hero-copy h1,
        html[lang="ar"] .section-head h2,
        html[lang="ar"] .cta-panel h2 {
            font-family: 'IBM Plex Sans Arabic', sans-serif;
            line-height: 1.15;
            letter-spacing: -0.03em;
        }

        .accent {
            color: var(--accent);
        }

        .hero-copy p {
            margin: 22px 0 0;
            max-width: 64ch;
            color: var(--muted);
            font-size: 1.06rem;
            line-height: 1.8;
        }

        .pill-row,
        .metric-strip,
        .cta-row {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .pill-row {
            margin-top: 24px;
        }

        .pill {
            padding: 11px 16px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--line);
            color: rgba(236, 249, 255, 0.92);
            font-size: 0.9rem;
        }

        .cta-row {
            margin-top: 28px;
        }

        .metric-strip {
            margin-top: 30px;
        }

        .metric-card {
            min-width: 150px;
            padding: 18px 20px;
            border-radius: 22px;
            background: linear-gradient(160deg, rgba(8, 31, 44, 0.94), rgba(6, 20, 30, 0.84));
            border: 1px solid var(--line);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }

        .metric-card strong {
            display: block;
            font-size: 1.55rem;
            font-family: 'Space Grotesk', 'Plus Jakarta Sans', sans-serif;
        }

        html[lang="ar"] .metric-card strong {
            font-family: 'IBM Plex Sans Arabic', sans-serif;
        }

        .metric-card span {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .hero-visual {
            position: relative;
        }

        .orbital-panel {
            position: relative;
            padding: 32px;
            border-radius: var(--radius-xl);
            border: 1px solid rgba(153, 230, 255, 0.12);
            background:
                radial-gradient(circle at top, rgba(89, 227, 255, 0.18), transparent 40%),
                linear-gradient(180deg, rgba(8, 30, 43, 0.92), rgba(4, 17, 25, 0.92));
            box-shadow: var(--shadow);
            overflow: hidden;
            isolation: isolate;
            display: grid;
            gap: 20px;
        }

        .orbital-stage {
            position: relative;
            min-height: 360px;
            padding: 30px;
            border-radius: 30px;
            display: grid;
            place-items: center;
            background:
                radial-gradient(circle at top, rgba(89, 227, 255, 0.14), transparent 42%),
                linear-gradient(180deg, rgba(7, 28, 39, 0.84), rgba(5, 20, 30, 0.92));
            border: 1px solid rgba(153, 230, 255, 0.10);
            overflow: hidden;
            isolation: isolate;
        }

        .orbital-stage::before,
        .orbital-stage::after {
            content: '';
            position: absolute;
            inset: 10%;
            border-radius: 50%;
            border: 1px dashed rgba(153, 230, 255, 0.12);
            z-index: -1;
        }

        .orbital-stage::after {
            inset: 22%;
            border-style: solid;
            opacity: 0.5;
        }

        .halo {
            position: absolute;
            inset: 50%;
            width: 320px;
            height: 320px;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            background: radial-gradient(circle, rgba(89, 227, 255, 0.24), transparent 65%);
            filter: blur(12px);
        }

        .logo-sphere {
            width: clamp(240px, 35vw, 340px);
            aspect-ratio: 1;
            border-radius: 50%;
            padding: 34px;
            display: grid;
            place-items: center;
            background:
                linear-gradient(160deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.02)),
                radial-gradient(circle at 20% 20%, rgba(159, 247, 219, 0.10), transparent 35%),
                rgba(6, 24, 35, 0.86);
            border: 1px solid rgba(159, 247, 219, 0.18);
            box-shadow:
                inset 0 1px 0 rgba(255, 255, 255, 0.09),
                0 30px 80px rgba(0, 0, 0, 0.34);
            position: relative;
            z-index: 1;
        }

        .logo-sphere img {
            width: 100%;
            height: auto;
            filter: drop-shadow(0 16px 34px rgba(17, 185, 218, 0.24));
        }

        .signal-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .signal-card {
            position: relative;
            width: 100%;
            padding: 18px 18px 16px;
            border-radius: 22px;
            background: rgba(4, 20, 29, 0.82);
            border: 1px solid rgba(153, 230, 255, 0.12);
            box-shadow: 0 18px 48px rgba(0, 0, 0, 0.18);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            min-height: 100%;
        }

        .signal-card strong {
            display: block;
            font-size: 1rem;
            margin-bottom: 8px;
        }

        .signal-card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 0.92rem;
        }

        .panel-summary {
            position: relative;
            padding: 18px 20px;
            border-radius: 24px;
            background: rgba(89, 227, 255, 0.10);
            border: 1px solid rgba(89, 227, 255, 0.12);
        }

        .panel-summary strong {
            display: block;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .panel-summary p {
            margin: 0;
            color: #d7f7ff;
            line-height: 1.7;
            font-size: 0.92rem;
        }

        .section {
            padding: 72px 0 0;
        }

        .section-head {
            max-width: 760px;
        }

        .section-head p {
            margin: 18px 0 0;
            color: var(--muted);
            line-height: 1.8;
            font-size: 1rem;
        }

        .card-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .feature-card,
        .privacy-card,
        .admin-card {
            padding: 26px;
            border-radius: var(--radius-lg);
            background: linear-gradient(160deg, rgba(7, 27, 40, 0.94), rgba(5, 20, 30, 0.94));
            border: 1px solid var(--line);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.04);
        }

        .feature-card .kicker,
        .privacy-card .kicker,
        .admin-card .kicker {
            display: inline-flex;
            margin-bottom: 14px;
            font-size: 0.78rem;
            color: var(--warm);
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .feature-card h3,
        .privacy-card h3,
        .admin-card h3 {
            margin: 0;
            font-size: 1.2rem;
        }

        .feature-card p,
        .privacy-card p,
        .admin-card p {
            margin: 12px 0 0;
            color: var(--muted);
            line-height: 1.8;
            font-size: 0.95rem;
        }

        .platform-layout {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, 0.95fr);
            gap: 22px;
            margin-top: 30px;
        }

        .journey-panel,
        .stack-panel {
            padding: 28px;
            border-radius: var(--radius-xl);
            background: linear-gradient(160deg, rgba(8, 31, 44, 0.94), rgba(6, 20, 30, 0.94));
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
        }

        .journey-panel h3,
        .stack-panel h3 {
            margin: 0 0 18px;
            font-size: 1.18rem;
        }

        .journey-list {
            display: grid;
            gap: 16px;
        }

        .journey-step {
            display: grid;
            grid-template-columns: 72px minmax(0, 1fr);
            gap: 16px;
            padding: 18px;
            border-radius: 22px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(153, 230, 255, 0.10);
        }

        .step-badge {
            display: grid;
            place-items: center;
            align-self: start;
            width: 72px;
            height: 72px;
            border-radius: 22px;
            background: linear-gradient(135deg, rgba(159, 247, 219, 0.16), rgba(89, 227, 255, 0.08));
            color: var(--warm);
            font-family: 'Space Grotesk', 'Plus Jakarta Sans', sans-serif;
            font-size: 1rem;
            font-weight: 700;
        }

        html[lang="ar"] .step-badge {
            font-family: 'IBM Plex Sans Arabic', sans-serif;
        }

        .step-copy {
            min-width: 0;
        }

        html[lang="ar"] .journey-step {
            grid-template-columns: minmax(0, 1fr) 72px;
        }

        html[lang="ar"] .step-copy {
            order: 1;
        }

        html[lang="ar"] .step-badge {
            order: 2;
        }

        .journey-step h4 {
            margin: 0;
            font-size: 1.05rem;
        }

        .journey-step p {
            margin: 8px 0 0;
            color: var(--muted);
            line-height: 1.7;
            font-size: 0.94rem;
        }

        .stack-list {
            display: grid;
            gap: 14px;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .stack-list li {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 16px 18px;
            border-radius: 18px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(153, 230, 255, 0.10);
            color: rgba(236, 249, 255, 0.92);
            line-height: 1.7;
        }

        .stack-list li::before {
            content: '';
            flex: 0 0 10px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-top: 8px;
            background: linear-gradient(135deg, var(--warm), var(--accent));
            box-shadow: 0 0 0 6px rgba(89, 227, 255, 0.08);
        }

        .cta-panel {
            margin-top: 32px;
            padding: 38px;
            border-radius: calc(var(--radius-xl) + 4px);
            background:
                radial-gradient(circle at top right, rgba(159, 247, 219, 0.18), transparent 30%),
                linear-gradient(150deg, rgba(10, 38, 53, 0.96), rgba(4, 17, 25, 0.96));
            border: 1px solid rgba(153, 230, 255, 0.16);
            box-shadow: var(--shadow);
        }

        .cta-panel p {
            margin: 16px 0 0;
            color: var(--muted);
            line-height: 1.85;
            max-width: 60ch;
        }

        .footer {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 72px;
            padding: 28px 0 0;
            border-top: 1px solid rgba(153, 230, 255, 0.10);
        }

        .footer-copy {
            max-width: 660px;
            color: var(--muted);
            line-height: 1.8;
        }

        .footer-mini {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .footer-mini a {
            padding: 12px 16px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(153, 230, 255, 0.10);
        }

        .reveal {
            opacity: 0;
            transform: translateY(18px);
            transition: opacity 0.55s ease, transform 0.55s ease;
        }

        .reveal.is-visible {
            opacity: 1;
            transform: translateY(0);
        }

        @media (max-width: 1120px) {
            .hero,
            .platform-layout,
            .card-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 820px) {
            .page-shell {
                width: min(var(--site-width), calc(100% - 20px));
                padding-top: 16px;
            }

            .topbar {
                border-radius: 28px;
                padding: 16px;
                flex-wrap: wrap;
            }

            .brand-lockup {
                flex: 1;
            }

            .menu-toggle {
                display: inline-flex;
                margin-inline-start: auto;
            }

            .nav-cluster {
                display: none;
                order: 3;
                width: 100%;
                padding-top: 16px;
                border-top: 1px solid rgba(153, 230, 255, 0.10);
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
                max-height: min(65vh, 420px);
                overflow-y: auto;
            }

            .topbar.menu-open .nav-cluster {
                display: flex;
            }

            .nav-links,
            .topbar-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .nav-links a {
                width: 100%;
                text-align: start;
            }

            .locale-switcher,
            .topbar .button {
                width: 100%;
            }

            .locale-switcher a {
                flex: 1;
                justify-content: center;
            }

            .hero {
                padding-top: 32px;
                gap: 26px;
            }

            .hero-copy h1,
            .section-head h2,
            .cta-panel h2 {
                font-size: clamp(2.2rem, 8vw, 3.7rem);
            }

            .orbital-panel,
            .orbital-stage {
                padding: 20px;
            }
        }

        @media (max-width: 980px) {
            .signal-grid {
                grid-template-columns: 1fr;
            }

            .orbital-stage {
                min-height: 320px;
            }
        }

        @media (max-width: 640px) {
            .cta-row,
            .metric-strip,
            .footer {
                flex-direction: column;
                align-items: stretch;
            }

            .locale-switcher {
                width: 100%;
            }

            .locale-switcher a {
                flex: 1;
                justify-content: center;
            }

            .button {
                width: 100%;
            }

            .orbital-panel {
                padding: 22px;
            }

            .orbital-stage {
                min-height: 220px;
                padding: 20px;
            }

            .halo {
                display: none;
            }

            .logo-sphere {
                width: min(220px, 100%);
                padding: 24px;
                margin: 0 auto;
            }

            .orbital-stage,
            .signal-card {
                padding: 18px;
            }

            .journey-step,
            html[lang="ar"] .journey-step {
                grid-template-columns: 1fr;
            }

            .step-badge {
                width: 64px;
                height: 64px;
            }
        }
    </style>
</head>
<body class="{{ $isRtl ? 'rtl' : 'ltr' }}">
    <div class="page-shell">
        <header class="topbar" id="topbar">
            <a class="brand-lockup" href="{{ $homeUrl }}">
                <img class="brand-mark" src="{{ asset('assets/front/rafiq-logo.png') }}" alt="{{ $copy['brand']['name'] }}">
                <div class="brand-copy">
                    <p class="brand-name">{{ $copy['brand']['name'] }}</p>
                    <p class="brand-tagline">{{ $copy['brand']['tagline'] }}</p>
                </div>
            </a>

            <button
                class="menu-toggle"
                id="menuToggle"
                type="button"
                aria-controls="site-navigation"
                aria-expanded="false"
                aria-label="{{ $copy['actions']['menu'] }}"
                data-open-label="{{ $copy['actions']['menu'] }}"
                data-close-label="{{ $copy['actions']['close_menu'] }}"
            >
                <span class="menu-toggle-text">{{ $copy['actions']['menu'] }}</span>
                <span class="menu-toggle-icon" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>

            <div class="nav-cluster" id="site-navigation">
                <nav class="nav-links" aria-label="{{ $copy['actions']['navigation_label'] }}">
                    <a href="#services">{{ $nav['services'] }}</a>
                    <a href="#platform">{{ $nav['platform'] }}</a>
                    <a href="#admin">{{ $nav['admin'] }}</a>
                    <a href="#privacy">{{ $nav['privacy'] }}</a>
                </nav>

                <div class="topbar-actions">
                    <div class="locale-switcher" aria-label="{{ $copy['actions']['switch_language'] }}">
                        @foreach ($localeLinks as $localeLink)
                            <a
                                href="{{ $localeLink['url'] }}"
                                class="{{ app()->getLocale() === $localeLink['code'] ? 'active' : '' }}"
                                lang="{{ $localeLink['code'] }}"
                            >
                                <span>{{ $localeLink['label'] }}</span>
                                <span>{{ $localeLink['native'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    <a class="button button-secondary" href="{{ $adminLoginUrl }}">{{ $copy['actions']['login'] }}</a>
                </div>
            </div>
        </header>

        @if (session('status'))
            <div class="status-banner reveal is-visible">{{ session('status') }}</div>
        @endif

        <section class="hero reveal is-visible">
            <div class="hero-copy">
                <span class="eyebrow">{{ $hero['eyebrow'] }}</span>
                <h1>
                    {{ $hero['title_lead'] }}
                    <span class="accent">{{ $hero['title_accent'] }}</span>
                </h1>
                <p>{{ $hero['description'] }}</p>

                <div class="pill-row">
                    @foreach ($hero['pills'] as $pill)
                        <span class="pill">{{ $pill }}</span>
                    @endforeach
                </div>

                <div class="cta-row">
                    <a class="button button-primary" href="{{ $adminLoginUrl }}">{{ $hero['primary_cta'] }}</a>
                    <a class="button button-secondary" href="#platform">{{ $hero['secondary_cta'] }}</a>
                </div>

                <div class="metric-strip">
                    @foreach ($hero['metrics'] as $metric)
                        <div class="metric-card">
                            <strong>{{ $metric['value'] }}</strong>
                            <span>{{ $metric['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="hero-visual">
                <div class="orbital-panel">
                    <div class="orbital-stage">
                        <div class="halo"></div>
                        <div class="logo-sphere">
                            <img src="{{ asset('assets/front/rafiq-logo.png') }}" alt="{{ $copy['brand']['name'] }}">
                        </div>
                    </div>

                    <div class="signal-grid">
                        @foreach ($hero['signals'] as $signal)
                            <article class="signal-card">
                                <strong>{{ $signal['title'] }}</strong>
                                <p>{{ $signal['text'] }}</p>
                            </article>
                        @endforeach
                    </div>

                    <div class="panel-summary">
                        <strong>{{ $hero['summary']['title'] }}</strong>
                        <p>{{ $hero['summary']['text'] }}</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section reveal" id="services">
            <div class="section-head">
                <span class="eyebrow">{{ $services['eyebrow'] }}</span>
                <h2>{{ $services['title'] }}</h2>
                <p>{{ $services['description'] }}</p>
            </div>

            <div class="card-grid">
                @foreach ($services['cards'] as $card)
                    <article class="feature-card">
                        <span class="kicker">{{ $card['kicker'] }}</span>
                        <h3>{{ $card['title'] }}</h3>
                        <p>{{ $card['text'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section reveal" id="platform">
            <div class="section-head">
                <span class="eyebrow">{{ $platform['eyebrow'] }}</span>
                <h2>{{ $platform['title'] }}</h2>
                <p>{{ $platform['description'] }}</p>
            </div>

            <div class="platform-layout">
                <div class="journey-panel">
                    <h3>{{ $platform['journey_title'] }}</h3>
                    <div class="journey-list">
                        @foreach ($platform['journey'] as $step)
                            <article class="journey-step">
                                <div class="step-badge">{{ $step['step'] }}</div>
                                <div class="step-copy">
                                    <h4>{{ $step['title'] }}</h4>
                                    <p>{{ $step['text'] }}</p>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>

                <div class="stack-panel">
                    <h3>{{ $platform['stack_title'] }}</h3>
                    <ul class="stack-list">
                        @foreach ($platform['stack'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>

        <section class="section reveal" id="admin">
            <div class="section-head">
                <span class="eyebrow">{{ $admin['eyebrow'] }}</span>
                <h2>{{ $admin['title'] }}</h2>
                <p>{{ $admin['description'] }}</p>
            </div>

            <div class="card-grid">
                @foreach ($admin['cards'] as $card)
                    <article class="admin-card">
                        <span class="kicker">{{ $card['kicker'] }}</span>
                        <h3>{{ $card['title'] }}</h3>
                        <p>{{ $card['text'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section reveal" id="privacy">
            <div class="section-head">
                <span class="eyebrow">{{ $privacy['eyebrow'] }}</span>
                <h2>{{ $privacy['title'] }}</h2>
                <p>{{ $privacy['description'] }}</p>
            </div>

            <div class="card-grid">
                @foreach ($privacy['cards'] as $card)
                    <article class="privacy-card">
                        <span class="kicker">{{ $card['kicker'] }}</span>
                        <h3>{{ $card['title'] }}</h3>
                        <p>{{ $card['text'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section reveal" id="cta">
            <div class="cta-panel">
                <span class="eyebrow">{{ $cta['eyebrow'] }}</span>
                <h2>{{ $cta['title'] }}</h2>
                <p>{{ $cta['description'] }}</p>

                <div class="cta-row">
                    <a class="button button-primary" href="{{ $adminLoginUrl }}">{{ $cta['primary_cta'] }}</a>
                    <a class="button button-secondary" href="#topbar">{{ $cta['secondary_cta'] }}</a>
                </div>
            </div>
        </section>

        <footer class="footer">
            <div class="footer-copy">
                <strong>{{ $copy['brand']['name'] }}</strong>
                <p>{{ $footer['note'] }}</p>
                <p>{{ $footer['copyright'] }}</p>
            </div>

            <div class="footer-mini">
                <a href="#services">{{ $nav['services'] }}</a>
                <a href="#platform">{{ $nav['platform'] }}</a>
                <a href="{{ $adminLoginUrl }}">{{ $copy['actions']['login'] }}</a>
            </div>
        </footer>
    </div>

    <script>
        (function () {
            const topbar = document.getElementById('topbar');
            const menuToggle = document.getElementById('menuToggle');
            const siteNavigation = document.getElementById('site-navigation');
            const revealItems = document.querySelectorAll('.reveal:not(.is-visible)');

            const syncTopbar = function () {
                if (!topbar) {
                    return;
                }

                topbar.classList.toggle('is-scrolled', window.scrollY > 10);
            };

            syncTopbar();
            window.addEventListener('scroll', syncTopbar, { passive: true });

            const setMenuState = function (isOpen) {
                if (!topbar || !menuToggle) {
                    return;
                }

                topbar.classList.toggle('menu-open', isOpen);
                menuToggle.classList.toggle('is-open', isOpen);
                menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                menuToggle.setAttribute('aria-label', isOpen ? menuToggle.dataset.closeLabel : menuToggle.dataset.openLabel);
            };

            if (menuToggle) {
                menuToggle.addEventListener('click', function () {
                    const isOpen = topbar && topbar.classList.contains('menu-open');
                    setMenuState(!isOpen);
                });

                window.addEventListener('resize', function () {
                    if (window.innerWidth > 820) {
                        setMenuState(false);
                    }
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        setMenuState(false);
                    }
                });
            }

            if (siteNavigation) {
                siteNavigation.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () {
                        setMenuState(false);
                    });
                });
            }

            if ('IntersectionObserver' in window) {
                const observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    });
                }, { threshold: 0.12 });

                revealItems.forEach(function (item) {
                    observer.observe(item);
                });
            } else {
                revealItems.forEach(function (item) {
                    item.classList.add('is-visible');
                });
            }
        })();
    </script>
</body>
</html>
