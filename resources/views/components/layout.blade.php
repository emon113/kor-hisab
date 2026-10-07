@props(['title' => null, 'bodyClass' => '', 'mainClass' => 'page'])
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}Kor Hishab</title>
    <meta name="description" content="Bangladesh income tax calculator for salaried people: slabs, investment rebate, predictions and saved calculations.">
    <meta name="theme-color" content="#0E5A43">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 22'%3E%3Crect width='30' height='22' rx='5' fill='%230E5A43'/%3E%3Ccircle cx='13' cy='11' r='6.2' fill='%23C8283A'/%3E%3C/svg%3E">
    <script>
        // Apply saved theme and number style before first paint (no flash).
        (function () {
            try {
                var t = localStorage.getItem('kh:theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', t);
            } catch (e) {}
        })();
    </script>
    <link rel="preload" href="{{ asset('fonts/bricolage-grotesque-latin-opsz-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ @filemtime(public_path('css/app.css')) }}">
    @stack('head')
</head>
<body class="{{ $bodyClass }}">
    <a class="skip-link" href="#main">Skip to content</a>

    <header class="topbar">
        <div class="topbar-inner">
            <a href="{{ route('home') }}" class="brand" aria-label="Kor Hishab home"><x-logo /></a>

            <nav class="mainnav" aria-label="Main">
                <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home', 'calculations.show')])>
                    <x-icon name="calculator" size="18" /> <span>Calculator</span>
                </a>
                <a href="{{ route('target') }}" @class(['active' => request()->routeIs('target')])>
                    <x-icon name="target" size="18" /> <span>Target tax</span>
                </a>
                @auth
                    <a href="{{ route('calculations.index') }}" @class(['active' => request()->routeIs('calculations.index', 'calculations.compare')])>
                        <x-icon name="folder" size="18" /> <span>Saved</span>
                    </a>
                @endauth
            </nav>

            <div class="topbar-tools">
                <button type="button" class="icon-btn" data-theme-toggle aria-label="Switch colour theme" title="Switch colour theme">
                    <x-icon name="moon" class="when-light" size="18" />
                    <x-icon name="sun" class="when-dark" size="18" />
                </button>
                @auth
                    <details class="menu">
                        <summary class="avatar" aria-label="Account menu">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</summary>
                        <div class="menu-panel">
                            <p class="menu-who"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></p>
                            <a href="{{ route('account') }}"><x-icon name="user" size="16" /> Account</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"><x-icon name="logout" size="16" /> Sign out</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('login') }}" class="btn btn-quiet">Sign in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm hide-sm">Create account</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main" class="{{ $mainClass }}">
        {{ $slot }}
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <p>Estimates under the Income Tax Act 2023 as amended by the Finance Act 2026. Not tax advice: confirm with NBR or a tax practitioner before filing.</p>
            <div class="grouping-toggle" role="group" aria-label="Number style">
                <span>Numbers</span>
                <button type="button" data-grouping="intl">1,335,524</button>
                <button type="button" data-grouping="lakh">13,35,524</button>
            </div>
        </div>
    </footer>

    <div class="toasts" aria-live="polite" id="toasts"></div>

    @if (session('status'))
        <script>window.__flash = @json(session('status'));</script>
    @endif
    <script src="{{ asset('vendor/chart-4.5.1.umd.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}"></script>
    @stack('scripts')
    <script defer src="{{ asset('vendor/alpine-3.17.4.min.js') }}"></script>
</body>
</html>
