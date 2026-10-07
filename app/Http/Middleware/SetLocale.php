<?php

namespace App\Http\Middleware;

use App\Support\Money;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the interface language: ?lang= (remembered in a cookie), then the
 * kh_locale cookie, then APP_LOCALE. Bangla also switches numbers to Bangla
 * digits unless the visitor chose Latin digits (kh_digits=latin, set from JS).
 */
class SetLocale
{
    public const LOCALES = ['en', 'bn'];

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->query('lang');
        if (is_string($requested) && in_array($requested, self::LOCALES, true) && $request->isMethod('GET') && ! $request->expectsJson()) {
            // Remember the choice and drop ?lang= so URLs stay clean and shareable.
            return redirect()->to($request->fullUrlWithoutQuery('lang'))
                ->withCookie(cookie()->forever('kh_locale', $requested));
        }

        // Cookie first (this device), then the signed-in user's saved choice, then APP_LOCALE.
        $prefs = $request->user()?->preferences ?? [];
        $locale = $request->cookie('kh_locale') ?: ($prefs['locale'] ?? null);
        if (! in_array($locale, self::LOCALES, true)) {
            $locale = in_array(config('app.locale'), self::LOCALES, true) ? config('app.locale') : 'en';
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);
        $digits = $request->cookie('kh_digits') ?: ($prefs['digits'] ?? null);
        Money::useDigits($locale === 'bn' && $digits !== 'latin' ? 'bn' : 'latin');

        return $next($request);
    }
}
