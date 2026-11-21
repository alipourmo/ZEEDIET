<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Display helpers used by the Blade directives @fa, @jdate and @money.
 *
 * Output is Persian: Persian digits, Jalali dates, and the Persian thousands separator (U+066C).
 */
final class Display
{
    private const CLINIC_TIMEZONE = 'Asia/Tehran';

    /** Arabic thousands separator used in Persian number formatting. */
    private const THOUSANDS_SEPARATOR = "\u{066C}";

    public static function fa(string|int|float $value): string
    {
        return Digits::toPersian($value);
    }

    /**
     * Formats a Toman amount with Persian digits and the Persian thousands separator, without the unit.
     */
    public static function moneyNumber(int $toman): string
    {
        $formatted = number_format($toman, 0, '.', ',');

        return Digits::toPersian(str_replace(',', self::THOUSANDS_SEPARATOR, $formatted));
    }

    /**
     * Formats an amount in Toman with the Persian unit word from lang/fa/common.php.
     */
    public static function money(int $toman): string
    {
        return self::moneyNumber($toman).' '.__('common.toman');
    }

    /**
     * Formats a date and time as Persian Jalali date and 24-hour time, e.g. ۱۴۰۳/۱۲/۳۰ ۱۴:۰۵.
     *
     * A DateTime is converted to Asia/Tehran. A string is read as local clinic time and is not converted.
     */
    public static function jdatetime(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $local = $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone(self::CLINIC_TIMEZONE))
            : new DateTimeImmutable($value, new DateTimeZone(self::CLINIC_TIMEZONE));

        $date = Jalali::formatGregorianAsJalali($local->format('Y-m-d'));

        return Digits::toPersian($date.' '.$local->format('H:i'));
    }

    /**
     * Formats a Gregorian date (or a DateTime, converted to Asia/Tehran) as a Persian Jalali date, e.g. ۱۴۰۳/۱۲/۳۰.
     */
    public static function jdate(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            $value = DateTimeImmutable::createFromInterface($value)
                ->setTimezone(new DateTimeZone(self::CLINIC_TIMEZONE))
                ->format('Y-m-d');
        }

        return Digits::toPersian(Jalali::formatGregorianAsJalali(substr($value, 0, 10)));
    }
}
