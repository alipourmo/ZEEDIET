<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RequestCodeRequest;
use App\Http\Requests\Portal\VerifyCodeRequest;
use App\Models\Client;
use App\Services\Audit\AuditLogger;
use App\Services\Portal\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Client sign-in with a one-time code (spec 7.5). The mobile is kept in the session between the two steps, so the
 * code form does not show it again.
 */
class PortalAuthController extends Controller
{
    private const SESSION_MOBILE = 'portal.mobile';

    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
    ) {}

    public function create(): RedirectResponse|View
    {
        if (Auth::guard('client')->check()) {
            return redirect()->route('portal.home');
        }

        return view('portal.auth.login');
    }

    public function store(RequestCodeRequest $request): RedirectResponse
    {
        $mobile = $request->mobile();

        $this->otp->request($mobile, (string) $request->ip());
        $request->session()->put(self::SESSION_MOBILE, $mobile);

        // The same reply for every number, so the answer does not show whether the number is a client's.
        return redirect()->route('portal.verify')->with('success', __('portal.code_sent'));
    }

    public function verifyForm(Request $request): RedirectResponse|View
    {
        if (! is_string($request->session()->get(self::SESSION_MOBILE))) {
            return redirect()->route('portal.login');
        }

        return view('portal.auth.verify');
    }

    public function verify(VerifyCodeRequest $request): RedirectResponse
    {
        $mobile = $request->session()->get(self::SESSION_MOBILE);

        $client = is_string($mobile) ? $this->otp->verify($mobile, $request->code(), (string) $request->ip()) : null;

        if (! $client instanceof Client) {
            return back()->with('error', __('portal.code_invalid'));
        }

        Auth::guard('client')->login($client);
        $request->session()->regenerate();
        $request->session()->forget(self::SESSION_MOBILE);
        $request->session()->put('portal.login_at', now()->timestamp);
        $request->session()->put('portal.seen_at', now()->timestamp);

        $this->audit->logAsClient(AuditAction::Login, $client, $client, ['method' => 'otp']);

        return redirect()->route('portal.home');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();

        if ($client instanceof Client) {
            $this->audit->logAsClient(AuditAction::Logout, $client, $client);
        }

        Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('success', __('portal.signed_out'));
    }
}
