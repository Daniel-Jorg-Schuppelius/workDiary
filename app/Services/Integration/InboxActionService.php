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

use App\Models\{ExternalReference, IntegrationInboxItem, Organization};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
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

    /** Konflikt zugunsten der lokalen Werte schließen (keine Änderung). */
    public function keepLocal(IntegrationInboxItem $item): void {
        $this->close($item, IntegrationInboxItem::STATUS_RESOLVED_LOCAL, $item->referenceable);
    }

    /** Eintrag verwerfen (bewusst nicht zuordnen). */
    public function dismiss(IntegrationInboxItem $item): void {
        $this->close($item, IntegrationInboxItem::STATUS_DISMISSED, null);
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
    }
}
