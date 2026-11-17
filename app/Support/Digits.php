<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Digit and letter normalisation for Persian UI.
 *
 * Storage and search use ASCII digits and Persian letters ی / ک.
 * Display uses Persian digits.
 */
final class Digits
{
    /** Eastern Arabic-Indic (Persian) digits, U+06F0..U+06F9. */
    private const PERSIAN_DIGITS = [
        "\u{06F0}", "\u{06F1}", "\u{06F2}", "\u{06F3}", "\u{06F4}",
        "\u{06F5}", "\u{06F6}", "\u{06F7}", "\u{06F8}", "\u{06F9}",
    ];

    /** Arabic-Indic digits, U+0660..U+0669. */
    private const ARABIC_DIGITS = [
        "\u{0660}", "\u{0661}", "\u{0662}", "\u{0663}", "\u{0664}",
        "\u{0665}", "\u{0666}", "\u{0667}", "\u{0668}", "\u{0669}",
    ];

    private const ASCII_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /** Arabic yeh U+064A and kaf U+0643 mapped to Persian yeh U+06CC and kaf U+06A9. */
    private const ARABIC_LETTERS = ["\u{064A}", "\u{0643}"];
    private const PERSIAN_LETTERS = ["\u{06CC}", "\u{06A9}"];

    /**
     * Converts Persian and Arabic digits to ASCII and maps Arabic ي/ك to Persian ی/ک.
     */
    public static function toAscii(string $value): string
    {
        $value = str_replace(self::PERSIAN_DIGITS, self::ASCII_DIGITS, $value);
        $value = str_replace(self::ARABIC_DIGITS, self::ASCII_DIGITS, $value);

        return str_replace(self::ARABIC_LETTERS, self::PERSIAN_LETTERS, $value);
    }

    /**
     * Converts ASCII digits to Persian digits for display. Other characters are unchanged.
     */
    public static function toPersian(string|int|float $value): string
    {
        return str_replace(self::ASCII_DIGITS, self::PERSIAN_DIGITS, (string) $value);
    }
}
