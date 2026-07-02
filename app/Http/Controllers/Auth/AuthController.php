<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            ActivityLog::record($request, 'Security', 'Failed login', 'Invalid credentials for '.$credentials['email'], 'failed');

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'These credentials do not match our records.']);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        ActivityLog::record($request, 'Security', 'Login', $request->user()->name.' signed in');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        ActivityLog::record($request, 'Security', 'Logout', $request->user()?->name.' signed out');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgotPassword(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        ActivityLog::record($request, 'Security', 'Password reset requested', $request->input('email'));

        // Do not reveal whether the email exists - always confirm.
        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            return back()->with('status', 'If an account exists for '.$request->input('email').', a reset link has been sent.');
        }

        return back()->withErrors(['email' => __($status)]);
    }

    public function showResetPassword(Request $request): View
    {
        return view('auth.reset-password', [
            'token' => $request->query('token'),
            'email' => $request->query('email'),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __($status)]);
        }

        ActivityLog::record($request, 'Security', 'Password reset completed', $request->input('email'));

        return redirect()->route('login')->with('status', 'Password has been reset. You can now sign in.');
    }
}
