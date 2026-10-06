<?php
/*
 * Created on   : Mon Oct 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AccountingMigrationItemStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Migration;

use App\Enums\Concerns\HasOptions;
use App\Enums\Contracts\HasLabel;

/** Stand einer Migrationsposition (MVP-653): Zuordnung Quelle → lokales Fachobjekt → Ziel. */
enum AccountingMigrationItemStatus: string implements HasLabel {
    use HasOptions;

    /** Erkannt, noch nicht entschieden. */
    case Pending = 'pending';

    /** Eindeutig zugeordnet (beide Fremd-IDs am selben lokalen Objekt). */
    case Matched = 'matched';

    /** Im Zielsystem angelegt/verknüpft. */
    case Transferred = 'transferred';

    /** Mehrdeutig oder verlustbehaftet — blockiert, Entscheidung nötig. */
    case Conflict = 'conflict';

    /** Bewusst übersprungen (z. B. archivierte Quelle). */
    case Skipped = 'skipped';

    /** Read-only Historie: bleibt im Quellsystem, wird nie nachgebaut. */
    case Historic = 'historic';

    /** Schreibversuch mit unklarem Ausgang oder Fehler. */
    case Failed = 'failed';

    public function label(): string {
        return (string) __('accounting_migration.status.' . $this->value);
    }

    /**
     * Stände, die eine Person als Entscheidung setzen darf.
     *
     * @return list<self>
     */
    public static function decisions(): array {
        return [self::Matched, self::Skipped, self::Historic, self::Conflict];
    }
}
