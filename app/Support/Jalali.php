<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Gregorian <-> Jalali (Persian solar calendar) conversion.
 *
 * Pure arithmetic, no extensions. Dates are stored as Gregorian in the database;
 * this class converts them for display and parses Jalali input from forms.
 *
 * Week starts on Saturday (see config/clinic.php weekend_days).
 */
final class Jalali
{
    /**
     * @return array{0:int,1:int,2:int} [jy, jm, jd]
     */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $gDayMonthOffsets = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        if ($gy > 1600) {
            $jy = 979;
            $gy -= 1600;
        } else {
            $jy = 0;
            $gy -= 621;
        }

        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 365 * $gy
            + intdiv($gy2 + 3, 4)
            - intdiv($gy2 + 99, 100)
            + intdiv($gy2 + 399, 400)
            - 80
            + $gd
            + $gDayMonthOffsets[$gm - 1];

        $jy += 33 * intdiv($days, 12053);
        $days %= 12053;

        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }

        return [$jy, $jm, $jd];
    }

    /**
     * @return array{0:int,1:int,2:int} [gy, gm, gd]
     */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        if ($jy > 979) {
            $gy = 1600;
            $jy -= 979;
        } else {
            $gy = 621;
        }

        $days = 365 * $jy
            + intdiv($jy, 33) * 8
            + intdiv(($jy % 33) + 3, 4)
            + 78
            + $jd
            + ($jm < 7 ? ($jm - 1) * 31 : ($jm - 7) * 30 + 186);

        $gy += 400 * intdiv($days, 146097);
        $days %= 146097;

        if ($days > 36524) {
            $days--;
            $gy += 100 * intdiv($days, 36524);
            $days %= 36524;

            if ($days >= 365) {
                $days++;
            }
        }

        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        $gd = $days + 1;
        $leap = self::isGregorianLeap($gy);
        $monthLengths = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

        $gm = 0;
        for ($gm = 1; $gm <= 12; $gm++) {
            $length = $monthLengths[$gm];
            if ($gd <= $length) {
                break;
            }
            $gd -= $length;
        }

        return [$gy, $gm, $gd];
    }

    public static function isJalaliLeap(int $jy): bool
    {
        [$gy, $gm, $gd] = self::toGregorian($jy, 12, 30);

        return self::fromGregorian($gy, $gm, $gd) === [$jy, 12, 30];
    }

    public static function daysInJalaliMonth(int $jy, int $jm): int
    {
        if ($jm >= 1 && $jm <= 6) {
            return 31;
        }

        if ($jm >= 7 && $jm <= 11) {
            return 30;
        }

        return self::isJalaliLeap($jy) ? 30 : 29;
    }

    public static function isValidJalali(int $jy, int $jm, int $jd): bool
    {
        if ($jy < 1 || $jm < 1 || $jm > 12 || $jd < 1) {
            return false;
        }

        return $jd <= self::daysInJalaliMonth($jy, $jm);
    }

    /**
     * Parses a Jalali date typed by a user, e.g. "۱۴۰۳/۱۲/۳۰" or "1403-12-30",
     * and returns the Gregorian date as "Y-m-d", or null when invalid.
     */
    public static function parseToGregorian(string $input): ?string
    {
        $normalized = Digits::toAscii(trim($input));

        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $normalized, $m) !== 1) {
            return null;
        }

        $jy = (int) $m[1];
        $jm = (int) $m[2];
        $jd = (int) $m[3];

        if (! self::isValidJalali($jy, $jm, $jd)) {
            return null;
        }

        [$gy, $gm, $gd] = self::toGregorian($jy, $jm, $jd);

        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /**
     * Formats a Gregorian "Y-m-d" date as Jalali "YYYY/MM/DD" with ASCII digits.
     * Use Digits::toPersian() on the result for display.
     */
    public static function formatGregorianAsJalali(string $gregorianYmd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $gregorianYmd, $m) !== 1) {
            throw new \InvalidArgumentException('Expected a Gregorian date in Y-m-d format.');
        }

        if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new \InvalidArgumentException('Invalid Gregorian date.');
        }

        [$jy, $jm, $jd] = self::fromGregorian((int) $m[1], (int) $m[2], (int) $m[3]);

        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    private static function isGregorianLeap(int $gy): bool
    {
        return ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
    }
}
