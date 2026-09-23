<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(['en', 'ar'])]]);
        $request->user()->update(['language' => $data['locale'] === 'ar' ? 'Arabic' : 'English']);
        $request->session()->put('locale', $data['locale']);

        // Return only to our own admin pages, never to an untrusted Referer origin.
        $previous = url()->previous();
        if (parse_url($previous, PHP_URL_HOST) !== $request->getHost()
            || ! str_starts_with((string) parse_url($previous, PHP_URL_PATH), '/admin/')) {
            $previous = route('admin.dashboard');
        }

        return redirect()->to($previous);
    }
}
