<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RemoteTask.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TaskSync;

use App\Enums\Task\TaskStatus;

/**
 * Normalisierte Quellaufgabe eines Aufgabenimports. `fields` enthält nur Felder,
 * die die Antwort tatsächlich trug — ein fehlendes Feld ist keine Löschung,
 * ein ausdrückliches `null` schon. `issues` nennt Klärfälle der Abbildung
 * (`unknown_status`, `empty_title`, `description_incomplete`).
 */
final readonly class RemoteTask {
    public const UNKNOWN_STATUS = 'unknown_status';

    public const EMPTY_TITLE = 'empty_title';

    public const DESCRIPTION_INCOMPLETE = 'description_incomplete';

    /**
     * @param  array{title?: string, description?: ?string, status?: string, due_date?: ?string}  $fields
     * @param  array{title: string, key?: ?string, url?: ?string, project?: ?string}  $snapshot
     * @param  list<string>  $issues
     */
    public function __construct(
        public string $id,
        public array $fields,
        public array $snapshot,
        public array $issues = [],
    ) {}

    public function isDone(): bool {
        return ($this->fields['status'] ?? null) === TaskStatus::Done->value;
    }

    public function has(string $issue): bool {
        return in_array($issue, $this->issues, true);
    }
}
