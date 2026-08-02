<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DisposalJobEventType.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Disposal;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Ereignisse der Nachweiskette einer Entsorgungsakte (Feature 100) —
 * append-only in disposal_job_events (Muster protocol_events).
 */
enum DisposalJobEventType: string implements HasLabel {
    use HasOptions;

    case Created = 'created';
    case ItemAdded = 'item_added';
    case ItemUpdated = 'item_updated';
    case ItemRemoved = 'item_removed';
    case TreatmentAdded = 'treatment_added';
    case TreatmentRemoved = 'treatment_removed';
    case HandoverAdded = 'handover_added';
    case HandoverRemoved = 'handover_removed';
    case StatusChanged = 'status_changed';
    case Signed = 'signed';
    case RecordRendered = 'record_rendered';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string {
        return match ($this) {
            self::Created => (string) __('Akte angelegt'),
            self::ItemAdded => (string) __('Geräteposition erfasst'),
            self::ItemUpdated => (string) __('Geräteposition geändert'),
            self::ItemRemoved => (string) __('Geräteposition entfernt'),
            self::TreatmentAdded => (string) __('Datenträger-Behandlung dokumentiert'),
            self::TreatmentRemoved => (string) __('Datenträger-Behandlung entfernt'),
            self::HandoverAdded => (string) __('Entsorger-Übergabe erfasst'),
            self::HandoverRemoved => (string) __('Entsorger-Übergabe entfernt'),
            self::StatusChanged => (string) __('Status geändert'),
            self::Signed => (string) __('Übernahme unterschrieben'),
            self::RecordRendered => (string) __('Kundennachweis erzeugt'),
            self::Completed => (string) __('Akte abgeschlossen'),
            self::Cancelled => (string) __('Akte storniert'),
        };
    }
}
