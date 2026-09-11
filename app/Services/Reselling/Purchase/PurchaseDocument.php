<?php
/*
 * Created on   : Fri Sep 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PurchaseDocument.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Reselling\Purchase;

use Carbon\CarbonImmutable;
use CommonToolkit\Enums\CurrencyCode;
use CommonToolkit\ValueObjects\Money;

/**
 * Anbieterneutraler Eingangsbeleg (Feature 152, Review 2026-09-11, Einkauf):
 * der Kern (pro-rata-Zuteilung, Zuteilungsdialog, Einkaufsliste) kennt nur
 * diese Sicht. Woher der Beleg kommt (Lexoffice-Spiegel, lokale Ausgabe,
 * Eingangs-E-Rechnung), sagt {@see $sourceKey}; {@see $morphClass}/{@see $morphId}
 * tragen den Bezug (`resale_purchase_entries.document_type/document_id`).
 */
final readonly class PurchaseDocument {
    public function __construct(
        public string $sourceKey,
        public string $morphClass,
        public int $morphId,
        /** Formularschlüssel (Sqid der Quelle) — die Quelle löst ihn über {@see PurchaseDocumentSource::byKey()} wieder auf. */
        public string $key,
        public ?string $number,
        public ?CarbonImmutable $date,
        public ?string $vendorName,
        /** Nettobetrag des Belegs; Quellen ohne Netto liefern die Belegsumme. */
        public Money $net,
        public CurrencyCode $currency,
        public ?string $description,
        /** Beleg im Quellsystem bzw. Detailseite (neues Fenster). */
        public ?string $permalink,
        /** Belegbild-Vorschau (Modal); null ohne Vorschau oder ohne Recht. */
        public ?string $previewUrl,
    ) {}

    /** Eindeutig über alle Quellen — Schlüssel für Zuteilungs- und Vorladetabellen. */
    public function identity(): string {
        return self::identityOf($this->morphClass, $this->morphId);
    }

    public static function identityOf(string $morphClass, int $morphId): string {
        return $morphClass . ':' . $morphId;
    }

    /** Kennung des Belegs: Nummer, sonst Beschreibung, sonst Lieferant. */
    public function reference(): string {
        foreach ([$this->number, $this->description, $this->vendorName] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '#' . $this->morphId;
    }

    /** Anzeige „dd.mm.yyyy · Kennung · Lieferant · Betrag". */
    public function label(): string {
        $parts = [];
        if ($this->date !== null) {
            $parts[] = $this->date->format('d.m.Y');
        }
        $reference = $this->reference();
        $parts[] = $reference;
        $vendor = trim((string) $this->vendorName);
        if ($vendor !== '' && $vendor !== $reference) {
            $parts[] = $vendor;
        }
        $parts[] = $this->net->format();

        return implode(' · ', $parts);
    }

    /** Suchtext für die Dialogfilterung (Nummer, Lieferant, Beschreibung). */
    public function haystack(): string {
        return mb_strtolower(trim((string) $this->number . ' ' . (string) $this->vendorName . ' ' . (string) $this->description));
    }
}
