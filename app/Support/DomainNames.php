<?php
/*
 * Created on   : Sat Oct 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DomainNames.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Domainnamen in Rechnungs- und Vorlagentexten (Feature 185): exakte Nennung
 * als eigenes Wort — „beispiel.de“ trifft nicht „beispiel.de.com“ und nicht
 * „sub.beispiel.de“ —, bei IDN auch die Unicode-Form, und alle Domainnamen eines
 * Textes. Genutzt vom Rechnungsspiegel ({@see \App\Services\Reselling\Mirror\MirrorLine})
 * und vom Vorlagenabgleich ({@see \App\Services\Reselling\Plan\PlanLinkProposer}, MVP-1442),
 * von der Domainverwaltung für die lesbare Anzeige — darum Plattform-Helfer.
 */
final class DomainNames {
    private const TOKEN = '/(?<![\p{L}\p{N}.-])((?:[\p{L}\p{N}](?:[\p{L}\p{N}-]*[\p{L}\p{N}])?\.)+[\p{L}]{2,})(?![\p{L}\p{N}-])/u';

    /**
     * Schreibweisen einer Domain: wie geführt und die jeweils andere IDN-Form — der
     * Anbieter führt „xn--onlinehndler-beispiel-c2b.de“, die Rechnung schreibt
     * „onlinehändler-beispiel.de“; das Register führt die lesbare Form als Bezeichnung.
     *
     * @return list<string>
     */
    public static function forms(string $domain): array {
        $domain = mb_strtolower(trim($domain));
        if ($domain === '') {
            return [];
        }
        $forms = [$domain];
        $other = str_contains($domain, 'xn--') ? self::display($domain) : self::ascii($domain);
        if ($other !== $domain) {
            $forms[] = $other;
        }

        return $forms;
    }

    /** Lesbare Form einer Domain: Punycode („xn--…“) als Unicode, sonst unverändert klein geschrieben. */
    public static function display(string $domain): string {
        $domain = mb_strtolower(trim($domain));
        if (! str_contains($domain, 'xn--') || ! function_exists('idn_to_utf8')) {
            return $domain;
        }
        $unicode = idn_to_utf8($domain, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);

        return is_string($unicode) && $unicode !== '' ? mb_strtolower($unicode) : $domain;
    }

    /** Punycode-Form einer Domain (so führt sie der Anbieter), sonst unverändert. */
    public static function ascii(string $domain): string {
        $domain = mb_strtolower(trim($domain));
        if (preg_match('/[^\x00-\x7f]/', $domain) !== 1 || ! function_exists('idn_to_ascii')) {
            return $domain;
        }
        $ascii = idn_to_ascii($domain, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);

        return is_string($ascii) && $ascii !== '' ? mb_strtolower($ascii) : $domain;
    }

    /** Nennt der Text die Domain exakt als eigenes Wort? */
    public static function mentioned(string $haystack, string $domain): bool {
        $haystack = mb_strtolower($haystack);
        foreach (self::forms($domain) as $form) {
            if (preg_match('/(?<![\p{L}\p{N}.-])' . preg_quote($form, '/') . '(?![\p{L}\p{N}-])/u', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Alle Domainnamen im Text, klein geschrieben, ohne Dubletten.
     *
     * @return list<string>
     */
    public static function tokens(string $haystack): array {
        preg_match_all(self::TOKEN, mb_strtolower($haystack), $m);

        return array_values(array_unique($m[1]));
    }
}
