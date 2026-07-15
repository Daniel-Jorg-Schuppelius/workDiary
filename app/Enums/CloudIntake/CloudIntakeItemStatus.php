<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CloudIntakeItemStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\CloudIntake;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Status eines Übergabenachweises (Feature 080). `source_gone` markiert
 * Tombstones (Quelle entfernt/entzogen) — lokale Nachweise/Dokumente werden
 * NIE automatisch gelöscht.
 */
enum CloudIntakeItemStatus: string implements HasLabel {
    use HasOptions;

    case Imported = 'imported';
    case Inbox = 'inbox';
    case Rejected = 'rejected';
    case Duplicate = 'duplicate';
    case SourceGone = 'source_gone';

    public function label(): string {
        return (string) __('enums.cloud_intake.item_status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Imported => 'success',
            self::Inbox => 'warning',
            self::Rejected => 'error',
            self::Duplicate => 'neutral',
            self::SourceGone => 'ghost',
        };
    }
}
