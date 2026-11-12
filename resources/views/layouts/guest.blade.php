<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#f07838">
        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" href="{{ asset('icons/erp-mark.svg') }}" type="image/svg+xml">
        <link rel="apple-touch-icon" href="{{ asset('images/out-of-boredom-logo.png') }}">
        <title>OUT OF BOREDOM</title>
        @include('layouts.theme-init')
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700&family=manrope:500,600,700,800&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-body">
        <div class="auth-shell">
            <header class="auth-nav">
                <a class="brand-lockup" href="{{ url('/') }}">
                    <img class="h-10 w-10 rounded-lg border border-orange-100 object-cover shadow-sm" src="{{ asset('images/out-of-boredom-logo.png') }}" alt="OUT OF BOREDOM logo">
                    <span class="brand-copy"><strong>OUT OF BOREDOM</strong><small>BUSINESS OWNER OVERVIEW</small></span>
                </a>
                <div class="auth-nav-actions">
                    <span class="auth-secure-note"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3"/></svg>Owner sign in</span>
                    <button class="icon-button theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode" title="Switch to dark mode" aria-pressed="false">
                        <svg class="theme-icon theme-icon-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"/></svg>
                        <svg class="theme-icon theme-icon-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"/></svg>
                    </button>
                </div>
            </header>
            <main class="auth-main">
                <section class="auth-intro">
                    <span class="landing-kicker"><span class="live-indicator"></span> OUT OF BOREDOM OWNER ACCESS</span>
                    @if (request()->routeIs('register'))
                        <h1>Let’s get your business<br><span>moving.</span></h1>
                        <p>Bring your team, inventory, and day-to-day operations together in one thoughtfully connected workspace.</p>
                    @else
                        <h1>Good to have<br><span>you back.</span></h1>
                        <p>Enter your six-digit owner PIN to continue to your business dashboard.</p>
                    @endif
                    <div class="auth-promise"><span class="auth-promise-icon"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/></svg></span><span><strong>One secure workspace</strong><small>Everything you need, right where you left it.</small></span></div>
                </section>
                <section class="auth-card" aria-labelledby="auth-title">
                    <div class="auth-card-heading">
                        @if (request()->routeIs('register'))
                            <span class="auth-step">LET’S GET YOU STARTED</span>
                            <h2 id="auth-title">Create your account</h2>
                            <p>Set up your access to the Out of Boredom workspace.</p>
                        @elseif (request()->routeIs('password.request', 'password.reset'))
                            <span class="auth-step">ACCOUNT RECOVERY</span>
                            <h2 id="auth-title">Reset your password</h2>
                            <p>We’ll help you get back into your workspace.</p>
                        @elseif (request()->routeIs('verification.notice'))
                            <span class="auth-step">ONE QUICK CHECK</span>
                            <h2 id="auth-title">Verify your email</h2>
                            <p>Confirm your address to secure your account.</p>
                        @elseif (request()->routeIs('password.confirm'))
                            <span class="auth-step">SECURE AREA</span>
                            <h2 id="auth-title">Confirm your password</h2>
                            <p>Please verify it’s you before continuing.</p>
                        @else
                            <span class="auth-step">SIX-DIGIT PIN ACCESS</span>
                            <h2 id="auth-title">Welcome back</h2>
                            <p>Enter your shared PIN to open the SMS Cafe dashboard.</p>
                        @endif
                    </div>
                    <div class="auth-form-content">
                        {{ $slot }}
                    </div>
                    <div class="auth-card-footer"><span>Protected access</span><span><i></i> Your workspace is private</span></div>
                </section>
            </main>
            <footer class="auth-footer"><span>© {{ date('Y') }} SMS Cafe</span><a href="{{ url('/') }}">Dashboard <span>→</span></a></footer>
        </div>
    </body>
</html>
