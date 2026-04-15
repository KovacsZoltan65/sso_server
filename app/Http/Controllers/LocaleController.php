<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $availableLocales = config('app.available_locales', ['hu', 'en']);

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($availableLocales)],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return redirect()->back();
    }
}
