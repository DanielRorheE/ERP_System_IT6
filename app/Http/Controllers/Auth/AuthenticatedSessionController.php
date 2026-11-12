<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        $pin = config('manager.shared_pin');
        $email = config('manager.user_email');
        $pinConfigured = is_string($pin)
            && preg_match('/^\d{6}$/', $pin) === 1
            && is_string($email)
            && Schema::hasTable('users')
            && DB::table('users')
                ->join('roles', 'roles.id', '=', 'users.role_id')
                ->where('users.email', $email)
                ->where('roles.name', 'owner')
                ->exists();

        return view('auth.login', ['pinConfigured' => $pinConfigured]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits:6'],
        ]);
        $throttleKey = 'manager-pin|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'pin' => 'Too many sign-in attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.',
            ]);
        }

        $configuredPin = config('manager.shared_pin');
        $userEmail = config('manager.user_email');

        abort_unless(
            is_string($configuredPin) && preg_match('/^\d{6}$/', $configuredPin) === 1 && is_string($userEmail) && $userEmail !== '',
            503,
            'PIN sign-in is not configured. Set MANAGER_SHARED_PIN and MANAGER_SHARED_PIN_USER_EMAIL.',
        );

        $user = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('users.email', $userEmail)
            ->where('roles.name', 'owner')
            ->first(['users.*']);
        abort_unless($user, 503, 'The configured account does not have the owner role.');

        if (! hash_equals($configuredPin, $validated['pin'])) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'pin' => 'The PIN is incorrect.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        Auth::loginUsingId((int) $user->id);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
