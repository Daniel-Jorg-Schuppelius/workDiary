<?php
/*
 * Created on   : Wed Jun 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Enums\Document;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Dokument-Status (MVP-031). `expired` wird im MVP nicht per Cron
 * persistiert, sondern aus `valid_until` berechnet angezeigt
 * ({@see \App\Models\Document::effectiveStatus()}); archivieren ist
 * eine manuelle Aktion.
 */
enum DocumentStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
    case Archived = 'archived';

    public function label(): string {
        return (string) __('enums.document.status.' . $this->value);
    }

    /** DaisyUI badge tone */
    public function tone(): string {
        return match ($this) {
            self::Draft => 'ghost',
            self::Active => 'success',
            self::Expired => 'error',
            self::Archived => 'ghost',
        };
    }
}
