<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'StudentHub') }} — Under Maintenance</title>

    <link rel="icon" href="/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f8fafc;
            color: #334155;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .container {
            text-align: center;
            max-width: 480px;
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 2rem;
            background: #e0f2fe;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon svg {
            width: 40px;
            height: 40px;
            color: #0284c7;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.75rem;
        }

        p {
            font-size: 1rem;
            line-height: 1.6;
            color: #64748b;
            margin-bottom: 1rem;
        }

        .status-badge {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.375rem 1rem;
            background: #f1f5f9;
            border-radius: 9999px;
            font-size: 0.8125rem;
            font-weight: 500;
            color: #64748b;
        }

        .status-badge .dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background: #f59e0b;
            border-radius: 50%;
            margin-right: 0.5rem;
            vertical-align: middle;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #0f172a;
                color: #94a3b8;
            }

            h1 { color: #f1f5f9; }

            .icon {
                background: #1e293b;
            }

            .icon svg {
                color: #38bdf8;
            }

            .status-badge {
                background: #1e293b;
                color: #94a3b8;
            }
        }

        @media (max-width: 480px) {
            .container { max-width: 100%; }
            h1 { font-size: 1.25rem; }
        }
    </style>
</head>
<body>
    @php
        $retryAfterSeconds = $retryAfter
            ?? (isset($exception) && $exception instanceof \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException
                ? $exception->getRetryAfter()
                : null);
    @endphp

    <div class="container">
        <div class="icon">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                <line x1="12" y1="9" x2="12" y2="13"/>
                <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
        </div>

        <h1>We'll be back soon!</h1>

        <p>
            {{ $retryAfterSeconds
                ? 'We\'re making some improvements. Please check back in a few minutes.'
                : 'We\'re performing scheduled maintenance to make things even better. This shouldn\'t take long.' }}
        </p>

        @if ($retryAfterSeconds)
            <p>Expected to be back by <strong>{{ now()->addSeconds((int) $retryAfterSeconds)->format('g:i A') }}</strong>.</p>
        @endif

        <div class="status-badge">
            <span class="dot"></span>
            Scheduled maintenance in progress
        </div>
    </div>
</body>
</html>
