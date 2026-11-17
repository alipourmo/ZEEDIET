<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Digits;
use App\Support\Mobile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Staff login and logout (spec sections 7.6 and 8.1).
 */
class LoginController extends Controller
{
    /** Failed attempts allowed per minute, per mobile and IP (spec section 7.6). */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(private readonly AuditLogger $audit) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $mobile = Mobile::normalize($request->validated('mobile')) ?? trim((string) $request->validated('mobile'));
        $throttleKey = 'staff-login|'.$mobile.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            return back()
                ->with('error', __('auth.throttled', ['seconds' => Digits::toPersian(RateLimiter::availableIn($throttleKey))]))
                ->withInput(['mobile' => $request->validated('mobile')]);
        }

        // is_active is part of the query, so a deactivated account fails with the same message as a wrong password.
        $attempted = Auth::attempt([
            'mobile' => $mobile,
            'password' => $request->validated('password'),
            'is_active' => true,
        ]);

        if (! $attempted) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

            return back()
                ->with('error', __('auth.failed'))
                ->withInput(['mobile' => $request->validated('mobile')]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = Auth::user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit->log(AuditAction::Login, $user, null, null, $user);

        if ($user->must_change_password) {
            return redirect()->route('password.change')->with('warning', __('auth.must_change_password'));
        }

        return redirect()->route($user->defaultPanelRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $this->audit->log(AuditAction::Logout, $user, null, null, $user);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', __('auth.logged_out'));
    }
}
