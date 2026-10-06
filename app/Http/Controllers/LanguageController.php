<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('fact.locales')))],
            'return_to' => ['nullable', 'string', 'max:512'],
        ]);
        $locale = $validated['locale'];
        $request->session()->put('locale', $locale);
        $returnTo = $this->safeReturnPath((string) ($validated['return_to'] ?? '/'));

        return redirect($returnTo)->withCookie(cookie(
            'fact_locale',
            $locale,
            60 * 24 * 365,
            '/',
            null,
            $request->isSecure() || (bool) config('session.secure'),
            true,
            false,
            'Lax',
        ));
    }

    private function safeReturnPath(string $path): string
    {
        $parts = parse_url($path);

        if (str_starts_with($path, '/')
            && ! str_starts_with($path, '//')
            && ! str_contains($path, '\\')
            && is_array($parts)
            && ! isset($parts['host'])
            && ! isset($parts['scheme'])) {
            return $path;
        }

        return route('home', absolute: false);
    }
}
