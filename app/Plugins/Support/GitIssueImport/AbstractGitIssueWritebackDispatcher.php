<?php
/*
 * Created on   : Thu Aug 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AbstractGitIssueWritebackDispatcher.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\GitIssueImport;

use App\Contracts\Integration\IntegrationOutboxDispatcher;
use App\Enums\Task\TaskStatus;
use App\Models\{ExternalReference, IntegrationOutboxEntry, Task};

/**
 * Status-Rückrichtung der Git-Issue-Plugins (Audit 2026-08, Welle 1.4):
 * eine in workDiary erledigte Aufgabe schließt das GitHub-/GitLab-Issue
 * (+ Erledigungs-Notiz), ein Wiedereröffnen öffnet es wieder. Bewusst NUR
 * der Status — Titel/Beschreibung bleiben quellsystem-geführt (kein
 * Fingerprint-Framework für Text-Konflikte; Schnittentscheidung 1.4).
 *
 * Der Dispatcher liest beim Zustellen den AKTUELLEN Task-Status (nicht den
 * Stand beim Enqueue) — verspätete Zustellungen setzen nie einen veralteten
 * Zustand. Remote-State-Setzen ist bei beiden APIs idempotent.
 */
abstract class AbstractGitIssueWritebackDispatcher implements IntegrationOutboxDispatcher {
    public const OP_SUFFIX_ISSUE_STATUS = '.issue.status';

    final public static function statusOperation(string $pluginId): string {
        return $pluginId . self::OP_SUFFIX_ISSUE_STATUS;
    }

    /** Ist die Status-Rückrichtung für diese Organisation freigeschaltet? */
    abstract public function writebackEnabled(int $organizationId): bool;

    /** ExternalReference-`external_type` der Issue-Verknüpfungen. */
    abstract public function externalType(): string;

    /** Setzt den Remote-Zustand; wirft die Plugin-API-Exception (Outbox-Retry). */
    abstract protected function applyState(int $organizationId, string $externalId, bool $closed): void;

    /** Erledigungs-Notiz beim Schließen; Fehler hier sind nachrangig (best effort). */
    abstract protected function comment(int $organizationId, string $externalId, string $body): void;

    public function dispatch(IntegrationOutboxEntry $entry): bool {
        if ($entry->operation !== self::statusOperation($this->pluginId())) {
            return true; // unbekannte Operation → nichts zu tun
        }

        if (! $this->writebackEnabled($entry->organization_id)) {
            return true; // inzwischen deaktiviert → erledigt
        }

        $payload = $entry->payload;
        $task = Task::query()
            ->withoutGlobalScopes()
            ->whereKey((int) ($payload['task_id'] ?? 0))
            ->where('organization_id', $entry->organization_id)
            ->first();
        if ($task === null) {
            return true; // Aufgabe weg — keine Löschweitergabe (bewusst)
        }

        $reference = ExternalReference::query()
            ->forPlugin($entry->organization_id, $this->pluginId(), $this->externalType())
            ->forReferenceable($task)
            ->first();
        if ($reference === null) {
            return true; // nicht (mehr) verknüpft
        }

        $closed = $task->status === TaskStatus::Done;
        $this->applyState($entry->organization_id, (string) $reference->external_id, $closed);

        if ($closed) {
            try {
                $this->comment($entry->organization_id, (string) $reference->external_id, (string) __('In workDiary als erledigt markiert.'));
            } catch (\Throwable) {
                // Der Zustand ist gesetzt — eine fehlende Notiz rechtfertigt
                // keinen Retry (der den State-Call wiederholen würde).
            }
        }

        return true;
    }
}
