<?php

namespace Upsoftware\Svarium\Http\Controllers;

use App\Http\Controllers\Controller;

class LocaleController extends Controller
{
    public function __invoke(string $locale) {
        session()->put('locale', $locale);
        app()->setLocale($locale);
        return back();
    }
}
