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
            self::Created => (string) __('enums.disposal.disposal_job_event_type.created'),
            self::ItemAdded => (string) __('enums.disposal.disposal_job_event_type.item_added'),
            self::ItemUpdated => (string) __('enums.disposal.disposal_job_event_type.item_updated'),
            self::ItemRemoved => (string) __('enums.disposal.disposal_job_event_type.item_removed'),
            self::TreatmentAdded => (string) __('enums.disposal.disposal_job_event_type.treatment_added'),
            self::TreatmentRemoved => (string) __('enums.disposal.disposal_job_event_type.treatment_removed'),
            self::HandoverAdded => (string) __('enums.disposal.disposal_job_event_type.handover_added'),
            self::HandoverRemoved => (string) __('enums.disposal.disposal_job_event_type.handover_removed'),
            self::StatusChanged => (string) __('enums.disposal.disposal_job_event_type.status_changed'),
            self::Signed => (string) __('enums.disposal.disposal_job_event_type.signed'),
            self::RecordRendered => (string) __('enums.disposal.disposal_job_event_type.record_rendered'),
            self::Completed => (string) __('enums.disposal.disposal_job_event_type.completed'),
            self::Cancelled => (string) __('enums.disposal.disposal_job_event_type.cancelled'),
        };
    }
}
