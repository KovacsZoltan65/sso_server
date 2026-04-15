<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse|Response|JsonResponse
    {
        $availableLocales = config('app.available_locales', ['hu', 'en']);

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in($availableLocales)],
        ]);

        $request->session()->put('locale', $validated['locale']);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->noContent();
        }

        return redirect()->back();
    }
}
