<?php

declare(strict_types=1);

namespace App\Http\Controllers\Specialist;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Specialist\SpecialistNotes;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The follow-ups due within the window, or overdue (spec 8.4). The route checks note.manage.
 */
class FollowUpController extends Controller
{
    public function __construct(private readonly SpecialistNotes $notes) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $today = CarbonImmutable::now('Asia/Tehran')->startOfDay();

        return view('specialist.followups.index', [
            'title' => __('followups.title'),
            'notes' => $this->notes->due($user, $today),
            'today' => $today,
            'days' => SpecialistNotes::FOLLOW_UP_WINDOW_DAYS,
        ]);
    }
}
