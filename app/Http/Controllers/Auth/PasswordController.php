<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The signed-in staff member changes their own password (spec section 7.6).
 */
class PasswordController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        // The 'hashed' cast hashes the value on save.
        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ])->save();

        // The changes are not logged: the password, old or new, never goes into audit_logs.
        $this->audit->log(AuditAction::PasswordChange, $user, null, null, $user);

        $request->session()->regenerate();

        return redirect()->route($user->defaultPanelRoute())->with('success', __('auth.password_changed'));
    }
}
