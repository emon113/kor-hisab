<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class LangController extends Controller
{
    /**
     * The translation table as a script, so JS-built text (charts, toasts,
     * live results) matches the page language. Cache-busted by ?v=<mtime>.
     */
    public function script(string $locale): Response
    {
        $file = lang_path("{$locale}.json");
        abort_unless(is_file($file), 404);

        return response('window.KH_LANG = '.file_get_contents($file).';', 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
