<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ConstructionNoticeStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Construction;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/**
 * Bearbeitungsstand eines VOB/B-Schreibens (H23, MVP-728). Nach `Sent` ist
 * das Schreiben festgeschrieben — nur noch der Eingangsvermerk des
 * Auftraggebers (`Acknowledged`) kommt hinzu.
 */
enum ConstructionNoticeStatus: string implements HasLabel {
    use HasOptions;

    case Draft = 'draft';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';

    public function label(): string {
        return match ($this) {
            self::Draft => __('construction.status.draft'),
            self::Sent => __('construction.status.sent'),
            self::Acknowledged => __('construction.status.acknowledged'),
        };
    }

    /** Entwuerfe sind aenderbar; alles ab dem Versand ist festgeschrieben. */
    public function isEditable(): bool {
        return $this === self::Draft;
    }
}
