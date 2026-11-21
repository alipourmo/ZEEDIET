<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\SmsStatus;
use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Sms\SmsDispatcher;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The SMS log and resend (spec sections 8.2 and 9). Admin and secretary: sms.log.view on every route.
 */
class SmsLogController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly SmsDispatcher $sms,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $request->validate(['status' => ['nullable', Rule::enum(SmsStatus::class)]]);

        $query = SmsLog::query()->latest('id');

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        return view('admin.sms.log.index', [
            'logs' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'status' => (string) $request->input('status', ''),
        ]);
    }

    public function resend(Request $request, SmsLog $log): RedirectResponse
    {
        $actor = $this->actor($request);
        $fresh = $this->sms->resend($log);

        $this->audit->log(AuditAction::Update, $fresh, null, ['new' => ['resent_of' => $log->getKey()]], $actor);

        return redirect()->route('admin.sms.log')->with('success', __('sms.log.resent'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
