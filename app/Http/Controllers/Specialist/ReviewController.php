<?php

declare(strict_types=1);

namespace App\Http\Controllers\Specialist;

use App\Enums\MeasurementSetStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Specialist\ReturnReviewRequest;
use App\Http\Requests\Specialist\ReviewValuesRequest;
use App\Models\MeasurementSet;
use App\Models\User;
use App\Services\Measurements\MeasurementRecorder;
use App\Services\Progress\ProgressService;
use App\Services\Reports\ReportWorkflow;
use App\Services\Reports\ReviewQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The specialist's review inbox and review page (spec 8.4). Every route is behind can:measurement.review. Only pending
 * sets can be opened; a published set is not on the review page.
 */
class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewQueue $queue,
        private readonly ReportWorkflow $workflow,
        private readonly MeasurementRecorder $recorder,
        private readonly ProgressService $progress,
    ) {}

    public function index(): View
    {
        return view('specialist.reviews.index', [
            'title' => __('reviews.inbox_title'),
            'sets' => $this->queue->pending(),
        ]);
    }

    public function show(Request $request, MeasurementSet $set): View
    {
        abort_unless($set->currentStatus() === MeasurementSetStatus::PendingReview, 404);

        $this->workflow->view($set, $this->actor($request));

        $set->load(['client', 'recorder', 'measurements.type']);
        $previous = $this->queue->previousFor($set);

        return view('specialist.reviews.show', [
            'title' => __('reviews.review_title'),
            'set' => $set,
            'previous' => $previous,
            'rows' => $this->queue->comparison($set, $previous),
            'chart' => $this->progress->chart($set->client),
        ]);
    }

    public function update(ReviewValuesRequest $request, MeasurementSet $set): RedirectResponse
    {
        $validated = $request->validated();
        $values = is_array($validated['values'] ?? null) ? $validated['values'] : [];

        $this->recorder->replaceValues($set, $this->actor($request), $values);

        return redirect()->route('specialist.reviews.show', $set)->with('success', __('reviews.saved'));
    }

    public function publish(Request $request, MeasurementSet $set): RedirectResponse
    {
        $this->workflow->publish($set, $this->actor($request));

        return redirect()->route('specialist.reviews.index')->with('success', __('reviews.published'));
    }

    public function returnToOperator(ReturnReviewRequest $request, MeasurementSet $set): RedirectResponse
    {
        $this->workflow->returnToOperator($set, $this->actor($request), (string) $request->validated('comment'));

        return redirect()->route('specialist.reviews.index')->with('success', __('reviews.returned'));
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
