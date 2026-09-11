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
        /** `lex:<id>` (Lexoffice-Artikel), `art:<id>` (lokaler Artikel) oder null. */
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
        if ($this->serviceFrom === null) {
            return null;
        }
        $label = $this->serviceFrom->format('d.m.Y');

        return $this->serviceTo === null ? $label : $label . ' – ' . $this->serviceTo->format('d.m.Y');
    }

    /** Lokale Artikel-ID aus `art:<id>`, sonst null. */
    public function localArticleId(): ?int {
        return $this->articleKey !== null && str_starts_with($this->articleKey, 'art:') ? (int) substr($this->articleKey, 4) : null;
    }

    /** Lexoffice-Artikel-ID aus `lex:<id>`, sonst null. */
    public function lexofficeArticleId(): ?int {
        return $this->articleKey !== null && str_starts_with($this->articleKey, 'lex:') ? (int) substr($this->articleKey, 4) : null;
    }
}
