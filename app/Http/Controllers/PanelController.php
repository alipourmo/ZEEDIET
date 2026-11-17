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
        return $this->panel('operator');
    }

    public function specialist(): View
    {
        return $this->panel('specialist');
    }

    private function panel(string $panel): View
    {
        return view('panels.home', [
            'title' => __('panels.'.$panel),
            'user' => Auth::user(),
        ]);
    }
}
