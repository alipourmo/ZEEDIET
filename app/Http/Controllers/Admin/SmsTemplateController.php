<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Enums\SmsTemplateKey;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sms\UpdateSmsTemplateRequest;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SMS template editing (spec sections 8.2 and 9). Admin only: sms.templates.manage on every route.
 */
class SmsTemplateController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.sms.templates.index', [
            'templates' => SmsTemplate::query()->orderBy('key')->get(),
        ]);
    }

    public function edit(SmsTemplate $template): View
    {
        $key = SmsTemplateKey::tryFrom($template->key);

        return view('admin.sms.templates.edit', [
            'template' => $template,
            'label' => $key?->label() ?? $template->key,
            'placeholders' => $key?->allowedPlaceholders() ?? [],
        ]);
    }

    public function update(UpdateSmsTemplateRequest $request, SmsTemplate $template): RedirectResponse
    {
        $old = ['body' => $template->body, 'is_active' => (bool) $template->is_active];
        $key = SmsTemplateKey::tryFrom($template->key);

        $template->forceFill([
            'body' => (string) $request->input('body'),
            'is_active' => $key?->isAlwaysOn() === true ? true : (bool) $request->input('is_active'),
        ])->save();

        $this->audit->log(AuditAction::Update, $template, null, [
            'old' => $old,
            'new' => ['body' => $template->body, 'is_active' => (bool) $template->is_active],
        ], $this->actor($request));

        return redirect()->route('admin.sms.templates')->with('success', __('sms.templates.saved'));
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
