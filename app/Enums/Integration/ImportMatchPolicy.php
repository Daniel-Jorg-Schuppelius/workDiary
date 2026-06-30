<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ImportMatchPolicy.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

/**
 * Steuert, was der {@see \App\Services\Integration\IntegrationResolver} tut, wenn
 * ein Remote-Datensatz keinem bestehenden lokalen Datensatz eindeutig zugeordnet
 * werden kann. Verallgemeinert die bisherige Lexoffice-spezifische
 * {@see \App\Plugins\Lexoffice\LexofficeMatchPolicy}.
 */
enum ImportMatchPolicy: string {
    /**
     * Default (Inbox-First): nur eindeutige Treffer werden automatisch verlinkt,
     * alles andere landet in der Zuordnungs-Inbox. NIE blind anlegen.
     */
    case AutoLinkExactOnly = 'auto_link';

    /**
     * Wie AutoLinkExactOnly, legt aber bei gar keinem Kandidaten einen neuen
     * Datensatz an (bewusstes Opt-in pro Importlauf/Entität).
     */
    case AutoLinkAndCreate = 'auto_create';

    /** Alles in die Inbox — auch eindeutige Treffer werden manuell bestätigt. */
    case ManualReview = 'manual';

    public function label(): string {
        return match ($this) {
            self::AutoLinkExactOnly => (string) __('Nur eindeutige zuordnen (Rest in die Inbox)'),
            self::AutoLinkAndCreate => (string) __('Zuordnen, sonst neu anlegen'),
            self::ManualReview => (string) __('Alles manuell prüfen'),
        };
    }

    public static function fromSetting(?string $value): self {
        return self::tryFrom((string) $value) ?? self::AutoLinkExactOnly;
    }
}
