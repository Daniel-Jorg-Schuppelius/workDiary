<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : InboxActionService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Models\{AuditLog, ExternalReference, IntegrationInboxItem, Organization};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{Auth, Request};
use RuntimeException;

/**
 * Führt die Anwender-Entscheidungen auf Zuordnungs-Inbox-Einträgen aus
 * (zuordnen / neu anlegen / Konflikt lösen / verwerfen) und schreibt dabei die
 * dauerhafte {@see ExternalReference}-Bindung.
 */
class InboxActionService {
    public function __construct(private readonly MatchProfileRegistry $registry) {}

    /** Ordnet den Eintrag einem bestehenden lokalen Datensatz zu. */
    public function assignTo(IntegrationInboxItem $item, Model $target): void {
        $this->writeReference($item, $target);
        $this->close($item, IntegrationInboxItem::STATUS_RESOLVED_LINKED, $target);
    }

    /** Legt einen neuen lokalen Datensatz aus dem gemappten Wertesatz an. */
    public function createFromItem(IntegrationInboxItem $item): Model {
        $profile = $this->registry->for($item->target_type);
        if ($profile === null) {
            throw new RuntimeException("Kein MatchProfile für {$item->target_type} registriert.");
        }

        $organization = Organization::query()->findOrFail($item->organization_id);
        $model = $profile->create($organization, $item->mapped_snapshot ?? []);

        $this->writeReference($item, $model);
        $this->close($item, IntegrationInboxItem::STATUS_RESOLVED_CREATED, $model);

        return $model;
    }

    /** Konflikt zugunsten der Remote-Werte lösen (abweichende Felder übernehmen). */
    public function acceptRemote(IntegrationInboxItem $item): void {
        $model = $item->referenceable;
        if (! $model instanceof Model) {
            throw new RuntimeException('Konflikt-Eintrag ohne lokalen Datensatz.');
        }

        $changes = array_intersect_key(
            $item->mapped_snapshot ?? [],
            array_flip($item->diff_fields ?? []),
        );
        if ($changes !== []) {
            $model->fill($changes)->save();
        }

        $this->close($item, IntegrationInboxItem::STATUS_RESOLVED_REMOTE, $model);
    }

    /** Konflikt zugunsten der lokalen Werte schließen — und lokal auch extern durchsetzen. */
    public function keepLocal(IntegrationInboxItem $item): void {
        $this->close($item, IntegrationInboxItem::STATUS_RESOLVED_LOCAL, $item->referenceable);

        // „Lokal behalten" heißt: der lokale Stand soll auch extern gelten —
        // sonst meldet der nächste Abgleich denselben Konflikt erneut. Hat das
        // Plugin einen Outbox-Dispatcher (MVP-114), wird die Übertragung der
        // Konfliktfelder enqueued; Plugins ohne Rückkanal bleiben unberührt.
        $model = $item->referenceable;
        if ($model instanceof Model
            && $item->case_type === IntegrationInboxItem::CASE_CONFLICT
            && app(IntegrationOutboxDispatcherResolver::class)->for($item->plugin_id) !== null) {
            app(IntegrationOutboxService::class)->enqueue(
                (int) $item->organization_id,
                $item->plugin_id,
                strtolower(class_basename($model)) . '.update',
                [
                    'external_id' => $item->external_id,
                    'fields' => (array) ($item->diff_fields ?? []),
                ],
                'inbox-keep-local:' . $item->getKey(),
                $model,
            );
        }
    }

    /** Eintrag verwerfen (bewusst nicht zuordnen). */
    public function dismiss(IntegrationInboxItem $item): void {
        $this->close($item, IntegrationInboxItem::STATUS_DISMISSED, null);
    }

    /**
     * Schließt einen Eintrag mit gegebenem Status + Audit (`integration.inbox_resolved`)
     * für plugin-spezifische Auflöser, die die Fachlogik selbst erledigen (z. B.
     * die WebDAV-Konfliktauflösung, Rang 18). Zentraler Abschluss statt Nachbau.
     */
    public function markResolved(IntegrationInboxItem $item, string $status, ?Model $resolvedTo = null): void {
        $this->close($item, $status, $resolvedTo);
    }

    private function writeReference(IntegrationInboxItem $item, Model $target): void {
        if ($item->external_id === null || $item->external_id === '') {
            return; // ohne stabile Fremd-ID keine dauerhafte Bindung
        }

        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => $item->plugin_id,
                'external_type' => $item->external_type,
                'referenceable_type' => $target->getMorphClass(),
                'referenceable_id' => $target->getKey(),
            ],
            [
                'organization_id' => $item->organization_id,
                'external_id' => $item->external_id,
                'payload' => $item->remote_snapshot,
                'synced_at' => now(),
            ],
        );
    }

    private function close(IntegrationInboxItem $item, string $status, ?Model $resolvedTo): void {
        $item->update([
            'status' => $status,
            'resolved_to_type' => $resolvedTo?->getMorphClass(),
            'resolved_to_id' => $resolvedTo?->getKey(),
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        // Nachvollziehbare Entscheidung (MVP-116): wer hat welchen Fall wie
        // gelöst — inkl. Konfliktfeldern, ohne Snapshot-Inhalte. user_id über
        // Auth::user() statt Auth::id() (vgl. Auditable::resolveAuditUserId).
        $actor = Auth::user();
        AuditLog::create([
            'organization_id' => $item->organization_id,
            'user_id' => $actor instanceof \App\Models\User ? (int) $actor->getKey() : null,
            'event' => 'integration.inbox_resolved',
            'auditable_type' => $item->getMorphClass(),
            'auditable_id' => $item->getKey(),
            'changes' => [
                'status' => $status,
                'case_type' => $item->case_type,
                'plugin_id' => $item->plugin_id,
                'external_id' => $item->external_id,
                'diff_fields' => $item->diff_fields,
            ],
            'ip' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
