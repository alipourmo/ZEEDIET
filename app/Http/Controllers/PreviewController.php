<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Digits;
use App\Support\Display;
use App\Support\Jalali;
use App\Support\Mobile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Read-only preview pages for stakeholder feedback. No data is stored.
 */
class PreviewController extends Controller
{
    private const MAX_INPUT_LENGTH = 40;

    public function home(): View
    {
        return view('preview.home', [
            'today' => Display::jdate(Carbon::now('Asia/Tehran')),
        ]);
    }

    public function jalali(Request $request): View
    {
        $input = $this->input($request, 'date');
        $gregorian = null;
        $invalid = false;

        if ($input !== '') {
            $gregorian = Jalali::parseToGregorian($input);
            $invalid = $gregorian === null;
        }

        $weekday = null;
        if ($gregorian !== null) {
            // PHP's date('w') starts at Sunday = 0. Clinic weekdays start at Saturday = 0 (spec section 6.2).
            $phpWeekday = (int) Carbon::parse($gregorian, 'Asia/Tehran')->format('w');
            $weekday = ($phpWeekday + 1) % 7;
        }

        return view('preview.jalali', [
            'input' => $input,
            'gregorian' => $gregorian === null ? null : Digits::toPersian($gregorian),
            'jalali' => $gregorian === null ? null : Display::jdate($gregorian),
            'weekday' => $weekday,
            'invalid' => $invalid,
        ]);
    }

    public function mobile(Request $request): View
    {
        $input = $this->input($request, 'mobile');
        $normalized = $input === '' ? null : Mobile::normalize($input);

        return view('preview.mobile', [
            'input' => $input,
            'normalized' => $normalized === null ? null : Display::fa($normalized),
            'invalid' => $input !== '' && $normalized === null,
        ]);
    }

    public function styleguide(): View
    {
        abort_unless(app()->environment('local'), 404);

        return view('preview.styleguide');
    }

    private function input(Request $request, string $key): string
    {
        $value = $request->string($key)->trim()->toString();

        return mb_substr(Digits::toAscii($value), 0, self::MAX_INPUT_LENGTH);
    }
}
