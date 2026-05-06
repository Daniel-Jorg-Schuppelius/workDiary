<?php

declare(strict_types=1);

/**
 * Global helper functions for convenient access to common utilities
 */

use CommonToolkit\Helper\Data\StringHelper as ToolkitStringHelper;
use CommonToolkit\Enums\Weekday;
use CommonToolkit\Enums\Month;

if (! function_exists('truncate')) {
    /**
     * Truncate a string to a maximum length with optional suffix.
     *
     * @param string|null $text The text to truncate
     * @param int $maxLength Maximum length (including suffix)
     * @param string $suffix Suffix to append (default: '...')
     * @param bool $trim Whether to trim the text first
     * @return string Truncated text or original if shorter
     */
    function truncate(?string $text, int $maxLength, string $suffix = '...', bool $trim = false): string {
        return ToolkitStringHelper::truncate($text, $maxLength, $suffix, $trim);
    }
}

if (! function_exists('isNullOrEmpty')) {
    /**
     * Check if a string is null or empty.
     *
     * @param string|null $value The value to check
     * @return bool True if null or empty string
     */
    function isNullOrEmpty(?string $value): bool {
        return ToolkitStringHelper::isNullOrEmpty($value);
    }
}

if (! function_exists('maskEmail')) {
    /**
     * Mask an email address for display purposes.
     *
     * @param string $email The email to mask
     * @param int $visibleStart Number of visible characters at start
     * @param int $visibleEnd Number of visible characters at end
     * @param string $maskChar Character to use for masking
     * @return string Masked email address
     */
    function maskEmail(string $email, int $visibleStart = 3, int $visibleEnd = 3, string $maskChar = '*'): string {
        return ToolkitStringHelper::mask($email, $visibleStart, $visibleEnd, $maskChar);
    }
}

if (! function_exists('weekdayAbbr')) {
    /**
     * Get weekday abbreviations for a week starting with Monday.
     * Uses the CommonToolkit Weekday enum with localization.
     *
     * @param string $locale Locale for names (default: 'de')
     * @param bool $long Return full names instead of abbreviations
     * @return array<int, string> Array of 7 weekday abbreviations/names (Mo-Su)
     */
    function weekdayAbbr(string $locale = 'de', bool $long = false): array {
        $days = [
            Weekday::MONDAY,
            Weekday::TUESDAY,
            Weekday::WEDNESDAY,
            Weekday::THURSDAY,
            Weekday::FRIDAY,
            Weekday::SATURDAY,
            Weekday::SUNDAY,
        ];

        return array_map(function (Weekday $day) use ($locale, $long): string {
            $name = $day->getName($locale);
            return $long ? $name : mb_substr($name, 0, 2);
        }, $days);
    }
}

if (! function_exists('monthsArray')) {
    /**
     * Get array of months for a given year with localization.
     * Uses the CommonToolkit Month enum.
     *
     * @param string $locale Locale for month names (default: 'de')
     * @param bool $leadingZero Whether to pad months with leading zeros (01-12)
     * @return array<string|int, string> Array of months [key => month name]
     */
    function monthsArray(string $locale = 'de', bool $leadingZero = false): array {
        return Month::toArray($leadingZero, $locale);
    }
}
