<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AuditFilterRequest;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

/**
 * Read-only audit log viewer for admins (spec sections 7.8 and 8.2). 50 entries per page.
 */
class AuditLogController extends Controller
{
    private const PER_PAGE = 50;

    public function index(AuditFilterRequest $request): View
    {
        $filters = $request->filters();

        $query = AuditLog::query()->orderByDesc('id');

        if ($filters['actor'] !== null) {
            $query->where('actor_type', 'user')->where('actor_id', $filters['actor']);
        }

        if ($filters['client'] !== null) {
            $query->where('client_id', $filters['client']);
        }

        if ($filters['action'] instanceof AuditAction) {
            $query->where('action', $filters['action']->value);
        }

        if ($filters['from'] !== null) {
            $query->where('created_at', '>=', Carbon::parse($filters['from'], 'Asia/Tehran')->startOfDay());
        }

        if ($filters['to'] !== null) {
            $query->where('created_at', '<=', Carbon::parse($filters['to'], 'Asia/Tehran')->endOfDay());
        }

        $logs = $query->paginate(self::PER_PAGE)->withQueryString();

        // One query for all actor names on the page, not one per row.
        $actorIds = $logs->getCollection()
            ->where('actor_type', 'user')
            ->pluck('actor_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        return view('admin.audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditAction::cases(),
            'staff' => User::query()->orderBy('name')->get(['id', 'name']),
            'actorNames' => User::query()->whereIn('id', $actorIds)->pluck('name', 'id')->all(),
        ]);
    }
}
