<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Operator\MyEntries;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The operator's own entries from the last seven days (spec 8.3). Read-only.
 */
class EntriesController extends Controller
{
    public function __construct(private readonly MyEntries $entries) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('operator.entries', [
            'title' => __('operator.entries_title'),
            'rows' => $this->entries->forOperator($user, CarbonImmutable::now('Asia/Tehran')),
        ]);
    }
}
