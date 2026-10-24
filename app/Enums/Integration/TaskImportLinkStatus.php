<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TaskImportLinkStatus.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Enums\Integration;

use App\Enums\Concerns\{HasOptions, HasTransitions};
use App\Enums\Contracts\HasStatusTransitions;

/**
 * Stand einer Projektzuordnung der Aufgabenimporte (Jira, Asana): erst nach
 * geprüfter Vorschau und ausdrücklicher Bestätigung läuft der Abgleich.
 */
enum TaskImportLinkStatus: string implements HasStatusTransitions {
    use HasOptions;
    use HasTransitions;

    case Pending = 'pending';
    case Active = 'active';
    case Paused = 'paused';

    public function label(): string {
        return (string) __('enums.integration.task_import_link_status.' . $this->value);
    }

    /** @return list<self> */
    public function allowedTransitions(): array {
        return match ($this) {
            self::Pending => [self::Active],
            self::Active => [self::Paused],
            self::Paused => [self::Active],
        };
    }

    public function tone(): string {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Paused => 'plain',
        };
    }
}
