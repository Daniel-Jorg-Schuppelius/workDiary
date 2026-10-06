<?php
/*
 * Created on   : Tue Oct 06 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrgaMaxInvoiceStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\OrgaMax\Enums;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;
use Orgamax\Enums\InvoiceState;

/**
 * Rechnungsstatus im lokalen Spiegel `orgamax_invoices` (Konsolidierungs-Audit
 * 2026-10, vierte Runde). Die Speicherwerte sind wörtlich die des SDK-Enums
 * {@see InvoiceState} — bewusst keine Übersetzung in App-Schreibweise, damit
 * Bestand, Belegfluss-CASE und API-Nutzlast dieselben Zeichenketten tragen.
 * `Unknown` fängt Altwerte der Datenmigration 2027_01_16 und Zustände, die das
 * SDK (noch) nicht kennt. orgaMAX führt den Status; die App schaltet ihn nie
 * um, deshalb kein Statusvertrag.
 */
enum OrgaMaxInvoiceStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Locked = 'locked';
    case PartiallyPaid = 'partiallyPaid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
    case Unknown = 'unknown';

    /** Das SDK liefert `null`, wenn orgaMAX einen Zustand meldet, den es nicht kennt. */
    public static function fromSdk(?InvoiceState $state): self {
        return self::fromStored($state?->value);
    }

    /** Gespeicherte oder projizierte Zeichenkette (Nutzlast der Referenz) — alles Fremde ist unbekannt. */
    public static function fromStored(?string $value): self {
        return self::tryFrom((string) $value) ?? self::Unknown;
    }

    public function label(): string {
        return (string) __('orgamax::orgamax.invoice_status.' . $this->value);
    }

    public function tone(): string {
        return match ($this) {
            self::Draft, self::Unknown => 'neutral',
            self::Locked => 'info',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::Cancelled => 'error',
        };
    }
}
