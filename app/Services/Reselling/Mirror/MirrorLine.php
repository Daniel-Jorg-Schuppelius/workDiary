<?php
/*
 * Created on   : Thu Sep 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MirrorLine.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Mirror;

use App\Services\Reselling\Register\LicenseMonths;
use App\Support\CarbonFmt;
use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Anbieterneutrale Rechnungsposition aus dem Belegspiegel (Feature 152,
 * Review 2026-09-10, Spiegel-Abstraktion). Der Kern (Vorschlagslauf,
 * Abgleich, Bezüge, Dialoge) kennt nur diese Sicht; woher die Position kommt
 * (Lexoffice-Spiegel, lokale Rechnung), sagt {@see $sourceKey}, und
 * {@see $morphClass}/{@see $morphId} tragen den Bezug (`resale_period_links`).
 *
 * Statusbegriffe sind neutral: `issued` (gültig, offen), `paid`, `draft`,
 * `voided` (storniert). Bei Gutschriften ist nur {@see $totalNet} negativ.
 */
final readonly class MirrorLine {
    public const STATUS_ISSUED = 'issued';

    public const STATUS_PAID = 'paid';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_VOIDED = 'voided';

    public function __construct(
        public string $sourceKey,
        public string $morphClass,
        public int $morphId,
        public int $organizationId,
        /** Formularschlüssel (Sqid der Quelle) — die Quelle löst ihn über {@see InvoiceMirrorSource::lineByKey()} wieder auf. */
        public string $key,
        /** Belegkennung innerhalb der Quelle (Gruppierung je Beleg). */
        public string $voucherKey,
        public ?string $voucherNumber,
        public ?CarbonImmutable $voucherDate,
        public string $voucherStatus,
        public bool $isCreditNote,
        /** Rechnungsempfänger als Kunde — null, wenn die Quelle ihn nicht kennt. */
        public ?int $recipientCustomerId,
        /** Empfängerschlüssel der Quelle (`contact:<id>`, `customer:<id>`) für Gruppierungen ohne Kunden. */
        public string $recipientKey,
        public ?string $recipientName,
        /** Katalogschlüssel ({@see \App\Services\Platform\Catalog\ArticleCatalog}: `art:<id>`, `lex:<id>`, …) oder null. */
        public ?string $articleKey,
        public ?string $articleName,
        /** Abo-Produkt laut Quelle (Einstufung/Namensmatch) — entscheidet, ob die Position eine Lizenzposition ist. */
        public bool $articleIsLicence,
        public string $name,
        public ?string $description,
        public float $quantity,
        public ?string $unitName,
        public Money $unitNet,
        public Money $totalNet,
        public CurrencyCode $currency,
        public ?CarbonImmutable $serviceFrom,
        public ?CarbonImmutable $serviceTo,
        public ?string $voucherText,
        /** Belegtext ohne Standardfloskeln (Endkundenhinweis), von der Quelle gekürzt. */
        public ?string $voucherTextHint,
        public int $position,
        /** Beleg im Quellsystem bzw. Detailseite (neues Fenster). */
        public ?string $permalink,
        /** Belegbild-Vorschau (Modal); null ohne Vorschau oder ohne Recht. */
        public ?string $previewUrl,
        /** Kennung des Belegs im Quellsystem (Lexoffice-UUID, lokale Rechnungsnummer) — trifft den Entwurfsstempel `draft_reference`. */
        public ?string $voucherExternalId = null,
        /** Schlüssel der Serienvorlage, aus der der Beleg entstand (`<quelle>:<id>`), sonst null (Feature 185). */
        public ?string $planKey = null,
    ) {}

    /** Eindeutig über alle Quellen — Schlüssel für Verbrauchs- und Bezugstabellen. */
    public function identity(): string {
        return self::identityOf($this->morphClass, $this->morphId);
    }

    public static function identityOf(string $morphClass, int $morphId): string {
        return $morphClass . ':' . $morphId;
    }

    /** Anzeigename der Position: Artikel, sonst Positionsname. */
    public function label(): string {
        $article = trim((string) $this->articleName);

        return $article !== '' ? $article : $this->name;
    }

    /** Positionsname samt Beschreibung (Endkundennennung, Zeitraum). */
    public function text(): string {
        return trim($this->name . ' ' . (string) $this->description);
    }

    /**
     * Nennt die Position diese Domain? Domainrechnungen tragen den Namen nur
     * im Text („.de-Domain inkl. Verwaltung (lastkraft-logistik.de)“), der
     * Artikel heißt „Top-Level-Domain“ — Wortgrenze, damit „lastkraft-logistik.de“
     * weder „sub.lastkraft-logistik.de“ noch „lastkraft-logistik.dev“ trifft.
     */
    public function mentionsDomain(string $domain): bool {
        $domain = mb_strtolower(trim($domain));
        if ($domain === '') {
            return false;
        }
        $forms = [$domain];
        // IDN: der Anbieter führt „xn--onlinehndler-beispiel-c2b.de“, die Rechnung schreibt „onlinehändler-beispiel.de“.
        if (str_contains($domain, 'xn--') && function_exists('idn_to_utf8')) {
            $unicode = idn_to_utf8($domain, IDNA_NONTRANSITIONAL_TO_UNICODE, INTL_IDNA_VARIANT_UTS46);
            if (is_string($unicode) && $unicode !== '' && $unicode !== $domain) {
                $forms[] = mb_strtolower($unicode);
            }
        }
        $haystack = mb_strtolower((string) $this->articleName . ' ' . $this->text());
        foreach ($forms as $form) {
            if (preg_match('/(?<![\p{L}\p{N}.-])' . preg_quote($form, '/') . '(?![\p{L}\p{N}-])/u', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Nennt die Position die Domain mit Tippfehler? Rechnungstexte werden von Hand geschrieben
     * („livescroing.eu“ für livescoring.eu, Pilot 2026-10-30). Gleiche Endung, erste Bezeichnung
     * bis zu ein (unter acht Zeichen) bzw. zwei Zeichen entfernt, der geschriebene Name ist keine
     * andere bekannte Domain, und keine andere bekannte Domain derselben Endung liegt gleich nah —
     * sonst false. Exakte Nennungen prüft {@see mentionsDomain()}; ein Treffer hier ist ein
     * unsicherer Vorschlag, keine automatische Bestätigung.
     *
     * @param  list<string>  $knownDomains  Domains aller Abos (Eindeutigkeit)
     */
    public function mentionsDomainLoosely(string $domain, array $knownDomains): bool {
        return $this->looselyMentionedDomain($domain, $knownDomains) !== null;
    }

    /**
     * Der im Text geschriebene Name, der die Domain mit Tippfehler meint ({@see mentionsDomainLoosely()}) — für die Notiz am Bezug.
     *
     * @param  list<string>  $knownDomains
     */
    public function looselyMentionedDomain(string $domain, array $knownDomains): ?string {
        $domain = mb_strtolower(trim($domain));
        if ($domain === '' || ! str_contains($domain, '.')) {
            return null;
        }
        [$label, $suffix] = explode('.', $domain, 2);
        $known = [];
        foreach ($knownDomains as $other) {
            $known[mb_strtolower(trim($other))] = true;
        }
        $allowed = mb_strlen($label) >= 8 ? 2 : 1;
        foreach ($this->domainTokens() as $token) {
            if (isset($known[$token]) || ! str_contains($token, '.')) {
                continue; // genau eine bekannte Domain geschrieben: kein Tippfehler
            }
            [$tokenLabel, $tokenSuffix] = explode('.', $token, 2);
            if ($tokenSuffix !== $suffix) {
                continue;
            }
            $distance = levenshtein($tokenLabel, $label);
            if ($distance > $allowed) {
                continue;
            }
            foreach (array_keys($known) as $other) {
                if ($other === $domain || ! str_ends_with($other, '.' . $suffix)) {
                    continue;
                }
                if (levenshtein(explode('.', $other, 2)[0], $tokenLabel) <= $distance) {
                    continue 2; // mehrdeutig: eine andere bekannte Domain liegt gleich nah oder näher
                }
            }

            return $token;
        }

        return null;
    }

    /**
     * Domain-ähnliche Wörter im Positionstext, klein geschrieben.
     *
     * @return list<string>
     */
    private function domainTokens(): array {
        $haystack = mb_strtolower((string) $this->articleName . ' ' . $this->text());
        preg_match_all('/(?<![\p{L}\p{N}.-])((?:[\p{L}\p{N}](?:[\p{L}\p{N}-]*[\p{L}\p{N}])?\.)+[\p{L}]{2,})(?![\p{L}\p{N}-])/u', $haystack, $m);

        return array_values(array_unique($m[1]));
    }

    /** Leistungsmonate aus dem Zeitraum (gerundet; Einzeldatum = null). */
    public function serviceMonths(): ?int {
        if ($this->serviceFrom === null || $this->serviceTo === null) {
            return null;
        }
        $months = LicenseMonths::monthsBetween($this->serviceFrom, $this->serviceTo);

        return $months > 0 ? $months : null;
    }

    /** Anzeige „dd.mm.yyyy – dd.mm.yyyy" bzw. nur der Beginn. */
    public function servicePeriodLabel(): ?string {
        return CarbonFmt::period($this->serviceFrom, $this->serviceTo);
    }

}
