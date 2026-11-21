<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Panel home pages. Real panel content is added in S2 (admin), S3 (operator) and S4 (specialist).
 */
class PanelController extends Controller
{
    public function admin(): View
    {
        return $this->panel('admin');
    }

    public function operator(): View
    {
        return $this->panel('operator', [
            'todayUrl' => route('operator.today'),
            'entriesUrl' => route('operator.entries'),
        ]);
    }

    public function specialist(): View
    {
        return $this->panel('specialist', [
            'reviewsUrl' => route('specialist.reviews.index'),
            'followUpsUrl' => route('specialist.followups'),
        ]);
    }

    /**
     * @param  array<string, string>  $links  Extra links for this panel's home page.
     */
    private function panel(string $panel, array $links = []): View
    {
        return view('panels.home', [
            'title' => __('panels.'.$panel),
            'user' => Auth::user(),
        ] + $links);
    }
}
