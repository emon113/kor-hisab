@props(['title' => null, 'bodyClass' => '', 'mainClass' => 'page'])
@php
    $locale = app()->getLocale();
    $otherLocale = $locale === 'bn' ? 'en' : 'bn';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ? $title.' — ' : '' }}{{ __('Kor Hishab') }}</title>
    <meta name="description" content="{{ __('Bangladesh income tax calculator for salaried people: slabs, investment rebate, predictions and saved calculations.') }}">
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
    <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

    <header class="topbar">
        <div class="topbar-inner">
            <a href="{{ route('home') }}" class="brand" aria-label="{{ __('Kor Hishab home') }}"><x-logo /></a>

            <nav class="mainnav" aria-label="{{ __('Main') }}">
                <a href="{{ route('home') }}" @class(['active' => request()->routeIs('home', 'calculations.show')])>
                    <x-icon name="calculator" size="18" /> <span>{{ __('Calculator') }}</span>
                </a>
                <a href="{{ route('target') }}" @class(['active' => request()->routeIs('target')])>
                    <x-icon name="target" size="18" /> <span>{{ __('Target tax') }}</span>
                </a>
                <details @class(['menu', 'nav-menu', 'active' => request()->routeIs('return-guide', 'calculations.guide', 'tds', 'filing-check', 'offers', 'tips')])>
                    <summary><x-icon name="tools" size="18" /> <span>{{ __('Tools') }}</span> <x-icon name="chevron" size="14" class="hide-sm" /></summary>
                    <div class="menu-panel">
                        <a href="{{ route('tips') }}" @class(['tool', 'active' => request()->routeIs('tips')])>
                            <x-icon name="sprout" size="18" />
                            <span><b>{{ __('Pay less tax, legally') }}</b><small>{{ __('Every choice the law gives you, in plain words') }}</small></span>
                        </a>
                        <a href="{{ route('offers') }}" @class(['tool', 'active' => request()->routeIs('offers')])>
                            <x-icon name="compare" size="18" />
                            <span><b>{{ __('Compare job offers') }}</b><small>{{ __('Which offer leaves more in your pocket after tax') }}</small></span>
                        </a>
                        <a href="{{ route('filing-check') }}" @class(['tool', 'active' => request()->routeIs('filing-check')])>
                            <x-icon name="help" size="18" />
                            <span><b>{{ __('Do I need to file?') }}</b><small>{{ __('Check the legal conditions in a minute') }}</small></span>
                        </a>
                        <a href="{{ route('tds') }}" @class(['tool', 'active' => request()->routeIs('tds')])>
                            <x-icon name="calendar" size="18" />
                            <span><b>{{ __('Monthly TDS planner') }}</b><small>{{ __('How much tax to deduct each month, so June has no surprise') }}</small></span>
                        </a>
                        <a href="{{ route('return-guide') }}" @class(['tool', 'active' => request()->routeIs('return-guide', 'calculations.guide')])>
                            <x-icon name="guide" size="18" />
                            <span><b>{{ __('Return form guide') }}</b><small>{{ __('Where each number goes on the NBR return') }}</small></span>
                        </a>
                    </div>
                </details>
                @auth
                    <a href="{{ route('calculations.index') }}" @class(['active' => request()->routeIs('calculations.index', 'calculations.compare')])>
                        <x-icon name="folder" size="18" /> <span>{{ __('Saved') }}</span>
                    </a>
                    <a href="{{ route('wealth.index') }}" @class(['active' => request()->routeIs('wealth.*')])>
                        <x-icon name="wealth" size="18" /> <span>{{ __('Wealth') }}</span>
                    </a>
                @endauth
            </nav>

            <div class="topbar-tools">
                <a href="{{ request()->fullUrlWithQuery(['lang' => $otherLocale]) }}" class="icon-btn lang-btn" hreflang="{{ $otherLocale }}" lang="{{ $otherLocale }}"
                   title="{{ $otherLocale === 'bn' ? 'বাংলায় দেখুন' : 'View in English' }}">{{ $otherLocale === 'bn' ? 'বাংলা' : 'EN' }}</a>
                <button type="button" class="icon-btn" data-theme-toggle aria-label="{{ __('Switch colour theme') }}" title="{{ __('Switch colour theme') }}">
                    <x-icon name="moon" class="when-light" size="18" />
                    <x-icon name="sun" class="when-dark" size="18" />
                </button>
                @auth
                    <details class="menu">
                        <summary class="avatar" aria-label="{{ __('Account menu') }}">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</summary>
                        <div class="menu-panel">
                            <p class="menu-who"><strong>{{ auth()->user()->name }}</strong><span>{{ auth()->user()->email }}</span></p>
                            <a href="{{ route('account') }}"><x-icon name="settings" size="16" /> {{ __('Settings') }}</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit"><x-icon name="logout" size="16" /> {{ __('Sign out') }}</button>
                            </form>
                        </div>
                    </details>
                @else
                    <a href="{{ route('login') }}" class="btn btn-quiet">{{ __('Sign in') }}</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm hide-sm">{{ __('Create account') }}</a>
                @endauth
            </div>
        </div>
    </header>

    <main id="main" class="{{ $mainClass }}">
        {{ $slot }}
    </main>

    <footer class="footer">
        <div class="footer-inner">
            <p>{{ __('Estimates under the Income Tax Act 2023 as amended by the Finance Act 2026. Not tax advice: confirm with NBR or a tax practitioner before filing.') }}</p>
            <div class="footer-toggles">
                @if ($locale === 'bn')
                    <div class="grouping-toggle" role="group" aria-label="{{ __('Digits') }}">
                        <span>{{ __('Digits') }}</span>
                        <button type="button" data-digits="bn">১২৩</button>
                        <button type="button" data-digits="latin">123</button>
                    </div>
                @endif
                <div class="grouping-toggle" role="group" aria-label="{{ __('Number style') }}">
                    <span>{{ __('Numbers') }}</span>
                    <button type="button" data-grouping="intl">{{ \App\Support\Money::digits('1,335,524') }}</button>
                    <button type="button" data-grouping="lakh">{{ \App\Support\Money::digits('13,35,524') }}</button>
                </div>
            </div>
        </div>
    </footer>

    <div class="toasts" aria-live="polite" id="toasts"></div>

    @if (session('status'))
        <script>window.__flash = @json(session('status'));</script>
    @endif
    @if (session('sync_display'))
        <script>
            // Settings saved on the account: copy them to this browser so JS formatting matches.
            (function (prefs) { try { Object.keys(prefs).forEach(function (k) { localStorage.setItem('kh:' + k, prefs[k]); }); } catch (e) {} })(@json(session('sync_display')));
        </script>
    @endif
    @if ($locale !== 'en')
        <script src="{{ route('lang.script', $locale) }}?v={{ @filemtime(lang_path($locale.'.json')) }}"></script>
    @endif
    <script src="{{ asset('vendor/chart-4.5.1.umd.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}?v={{ @filemtime(public_path('js/app.js')) }}"></script>
    @stack('scripts')
    <script defer src="{{ asset('vendor/alpine-3.17.4.min.js') }}"></script>
</body>
</html>
