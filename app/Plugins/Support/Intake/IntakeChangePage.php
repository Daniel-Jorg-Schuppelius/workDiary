<?php
/*
 * Created on   : Tue Jul 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntakeChangePage.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Support\Intake;

/**
 * Eine Seite des Delta-Laufs (Feature 080, MVP-351). Der Checkpoint gilt
 * erst als fortgeschrieben, wenn die Seite vollständig verarbeitet wurde
 * (Konzept §„Vorschau und Erstabgleich"); bei `hasMore` ruft der Runner
 * unmittelbar mit dem neuen Checkpoint weiter ab.
 */
final readonly class IntakeChangePage {
    public function __construct(
        /** @var list<IntakeItem> */
        public array $items,
        /** @var list<string> Provider-Item-IDs gelöschter/entzogener Dateien. */
        public array $tombstones,
        /** Checkpoint NACH dieser Seite (Cursor/deltaLink/pageToken). */
        public string $checkpoint,
        public bool $hasMore = false,
    ) {}
}
