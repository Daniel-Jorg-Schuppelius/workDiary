<?php

/*
 * Created on   : Wed May 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : helpers.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

/**
 * Globale Helper-Funktionen.
 *
 * Dünne Wrapper um App-Facades (app/Support/Toolkit/*), die wiederum
 * CommonToolkit-Funktionen kapseln. Für neue Code-Pfade bitte direkt
 * die Facade-Klassen nutzen; diese Funktionen bleiben aus Kompatibilitäts-
 * gründen für Views und Legacy-Pfade bestehen.
 */

use App\Models\Organization;
use App\Support\Toolkit\DateFacade;
use App\Support\Toolkit\StringFacade;
use CommonToolkit\Enums\Month;

if (! function_exists('truncate')) {
    function truncate(?string $text, int $maxLength, string $suffix = '...', bool $trim = false): string
    {
        return StringFacade::truncate($text, $maxLength, $suffix, $trim);
    }
}

if (! function_exists('isNullOrEmpty')) {
    function isNullOrEmpty(?string $value): bool
    {
        return StringFacade::isNullOrEmpty($value);
    }
}

if (! function_exists('maskEmail')) {
    function maskEmail(string $email, int $visibleStart = 3, int $visibleEnd = 3, string $maskChar = '*'): string
    {
        return StringFacade::mask($email, $visibleStart, $visibleEnd, $maskChar);
    }
}

if (! function_exists('weekdayAbbr')) {
    /**
     * @return list<string>
     */
    function weekdayAbbr(string $locale = 'de', bool $long = false): array
    {
        return DateFacade::weekdayAbbreviations($locale, $long);
    }
}

if (! function_exists('printable_initials')) {
    function printable_initials(?string $name, int $maxParts = 3): string
    {
        return StringFacade::printableInitials($name, $maxParts);
    }
}

if (! function_exists('monthsArray')) {
    /**
     * @return array<string|int, string>
     */
    function monthsArray(string $locale = 'de', bool $leadingZero = false): array
    {
        return Month::toArray($leadingZero, $locale);
    }
}

if (! function_exists('setting')) {
    /**
     * Tenant-aware configuration accessor.
     *
     * Resolution order:
     *   1. Organization::settings[<group>][<rest>] when an active org is bound
     *   2. config('<group>.<rest>') (file-based default, env-overridable)
     *   3. $default (hard fallback)
     *
     * Example:  setting('pagination.customers', 25)
     */
    function setting(string $key, mixed $default = null): mixed
    {
        [$group, $rest] = array_pad(explode('.', $key, 2), 2, null);
        if ($group === null || $rest === null || $rest === '') {
            return config($key, $default);
        }

        if (app()->bound('currentOrganization')) {
            $org = app('currentOrganization');
            if ($org instanceof Organization) {
                /** @var array<string, mixed> $settings */
                $settings = (array) ($org->settings ?? []);
                /** @var array<string, mixed> $stored */
                $stored = (array) ($settings[$group] ?? []);
                $value = data_get($stored, $rest, \INF);
                if ($value !== \INF) {
                    return $value;
                }
            }
        }

        return config($key, $default);
    }
}
