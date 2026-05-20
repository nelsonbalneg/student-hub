<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Portal | Scheduled Maintenance</title>

    <link rel="icon" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
        }

        :root {
            color-scheme: light dark;
            --page: #eef2f7;
            --panel: #ffffff;
            --panel-muted: #f8fafc;
            --ink: #101828;
            --muted: #667085;
            --line: #d9e2ec;
            --brand: #075985;
            --brand-strong: #0f766e;
            --accent: #b45309;
            --accent-soft: #fff7ed;
            --success: #0f766e;
            --shadow: 0 24px 70px rgba(15, 23, 42, 0.14);
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background:
                linear-gradient(rgba(255, 255, 255, 0.64) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.64) 1px, transparent 1px),
                var(--page);
            background-size: 44px 44px;
            color: var(--ink);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px;
        }

        .shell {
            width: min(100%, 960px);
            overflow: hidden;
            border: 1px solid rgba(16, 24, 40, 0.08);
            border-radius: 8px;
            background: var(--panel);
            box-shadow: var(--shadow);
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            padding: 22px 28px;
            border-bottom: 1px solid var(--line);
            background: rgba(248, 250, 252, 0.86);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .brand-mark {
            display: grid;
            width: 40px;
            height: 40px;
            flex: 0 0 auto;
            place-items: center;
            border-radius: 8px;
            background: var(--brand);
            color: #ffffff;
            font-weight: 800;
            letter-spacing: 0;
        }

        .brand-text {
            min-width: 0;
        }

        .brand-name {
            margin: 0;
            color: var(--ink);
            font-size: 15px;
            font-weight: 700;
            line-height: 1.25;
        }

        .brand-label {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            line-height: 1.4;
            text-transform: uppercase;
        }

        .status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            flex: 0 0 auto;
            border: 1px solid #fed7aa;
            border-radius: 999px;
            background: var(--accent-soft);
            color: #9a3412;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 700;
            line-height: 1;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--accent);
            box-shadow: 0 0 0 5px rgba(180, 83, 9, 0.12);
            animation: pulse 1.8s ease-in-out infinite;
        }

        .content {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            min-height: 440px;
        }

        .message {
            padding: 56px;
        }

        .eyebrow {
            margin: 0 0 16px;
            color: var(--brand-strong);
            font-size: 13px;
            font-weight: 800;
            line-height: 1.3;
            text-transform: uppercase;
        }

        h1 {
            max-width: 720px;
            margin: 0;
            color: var(--ink);
            font-size: clamp(32px, 6vw, 56px);
            font-weight: 800;
            line-height: 1.02;
            letter-spacing: 0;
        }

        .lead {
            max-width: 650px;
            margin: 22px 0 0;
            color: #475467;
            font-size: 18px;
            line-height: 1.65;
        }

        .note {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            max-width: 650px;
            margin-top: 34px;
            padding: 18px 0 0;
            border-top: 1px solid var(--line);
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .note svg {
            width: 20px;
            height: 20px;
            flex: 0 0 auto;
            color: var(--success);
        }

        .details {
            border-left: 1px solid var(--line);
            background: var(--panel-muted);
            padding: 40px 32px;
        }

        .details-title {
            margin: 0 0 22px;
            color: var(--ink);
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .detail-list {
            display: grid;
            gap: 20px;
            margin: 0;
        }

        .detail-item {
            padding-bottom: 20px;
            border-bottom: 1px solid var(--line);
        }

        .detail-item:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            margin: 0 0 6px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .detail-value {
            margin: 0;
            color: var(--ink);
            font-size: 15px;
            font-weight: 700;
            line-height: 1.45;
        }

        .detail-value.secondary {
            color: #475467;
            font-weight: 600;
        }

        .footer {
            display: flex;
            justify-content: flex-end;
            padding: 18px 28px;
            border-top: 1px solid var(--line);
            background: #ffffff;
            color: var(--muted);
            font-size: 12px;
            font-weight: 600;
            line-height: 1.5;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.42; }
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --page: #111827;
                --panel: #172033;
                --panel-muted: #121a2a;
                --ink: #f8fafc;
                --muted: #9aa7b8;
                --line: rgba(226, 232, 240, 0.12);
                --brand: #0e7490;
                --brand-strong: #5eead4;
                --accent: #f59e0b;
                --accent-soft: rgba(245, 158, 11, 0.12);
                --success: #5eead4;
                --shadow: 0 24px 70px rgba(0, 0, 0, 0.36);
            }

            body {
                background:
                    linear-gradient(rgba(255, 255, 255, 0.035) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255, 255, 255, 0.035) 1px, transparent 1px),
                    var(--page);
            }

            .topbar {
                background: rgba(18, 26, 42, 0.86);
            }

            .status {
                border-color: rgba(245, 158, 11, 0.26);
                color: #fbbf24;
            }

            .lead,
            .detail-value.secondary {
                color: #cbd5e1;
            }

            .footer {
                background: #172033;
            }
        }

        @media (max-width: 760px) {
            body {
                align-items: flex-start;
                padding: 18px;
            }

            .topbar,
            .footer {
                align-items: flex-start;
            }

            .topbar {
                flex-direction: column;
            }

            .content {
                grid-template-columns: 1fr;
            }

            .message {
                padding: 38px 26px;
            }

            .details {
                border-left: 0;
                border-top: 1px solid var(--line);
                padding: 28px 26px;
            }

            .lead {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    @php
        $appName = 'Student Portal';
        $retryHeader = isset($exception) && is_object($exception) && method_exists($exception, 'getHeaders')
            ? ($exception->getHeaders()['Retry-After'] ?? null)
            : null;

        $retryAfter = is_numeric($retryHeader)
            ? max((int) $retryHeader, 0)
            : max(((int) strtotime((string) $retryHeader)) - now()->timestamp, 0);

        $returnTime = $retryAfter > 0
            ? now()->addSeconds($retryAfter)->format('g:i A')
            : null;
    @endphp

    <main class="shell" aria-labelledby="maintenance-title">
        <header class="topbar">
            <div class="brand" aria-label="{{ $appName }}">
                <div class="brand-mark" aria-hidden="true">S</div>
                <div class="brand-text">
                    <p class="brand-name">{{ $appName }}</p>
                    <p class="brand-label">Service notice</p>
                </div>
            </div>

            <div class="status">
                <span class="status-dot" aria-hidden="true"></span>
                Maintenance in progress
            </div>
        </header>

        <section class="content">
            <div class="message">
                <p class="eyebrow">Temporary service interruption</p>
                <h1 id="maintenance-title">We are upgrading the platform.</h1>

                <p class="lead">
                    {{ $returnTime
                        ? "Student Portal is temporarily unavailable while scheduled maintenance is completed. Service is expected to resume by {$returnTime}. We apologize for the inconvenience caused."
                        : 'Student Portal is temporarily unavailable due to scheduled maintenance. Our team is working to restore access as quickly as possible. We apologize for the inconvenience caused.' }}
                </p>

                <div class="note">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                    <span>Your data remains secure. Open sessions, submissions, and account records are protected while maintenance is underway.</span>
                </div>
            </div>

            <aside class="details" aria-label="Maintenance details">
                <p class="details-title">Current status</p>

                <dl class="detail-list">
                    <div class="detail-item">
                        <dt class="detail-label">Availability</dt>
                        <dd class="detail-value">Temporarily offline</dd>
                    </div>

                    <div class="detail-item">
                        <dt class="detail-label">Estimated return</dt>
                        <dd class="detail-value">{{ $returnTime ?? 'In progress' }}</dd>
                    </div>

                    <div class="detail-item">
                        <dt class="detail-label">Recommended action</dt>
                        <dd class="detail-value secondary">Please try again shortly. No further action is required.</dd>
                    </div>
                </dl>
            </aside>
        </section>

        <footer class="footer">
            <span>UICTO Systems Development Team</span>
        </footer>
    </main>
</body>
</html>
