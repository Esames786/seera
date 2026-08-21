<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(): View
    {
        return view('admin.password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'That is not your current password.',
        ]);

        $user = $request->user();

        if (hash_equals($data['password'], $data['current_password'])) {
            return back()->withErrors(['password' => 'Choose a password that is different from your current one.']);
        }

        $user->update([
            'password' => $data['password'],
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        ActivityLog::record($request, 'Security', 'Changed own password', $user->name);

        return redirect()->route('admin.dashboard')
            ->with('status', 'Your password has been updated. Welcome to Seera ERP.');
    }
}
