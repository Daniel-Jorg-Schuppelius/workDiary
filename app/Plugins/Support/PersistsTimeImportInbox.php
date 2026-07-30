<?php
/*
 * Created on   : Thu Jul 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PersistsTimeImportInbox.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support;

use App\Models\{ExternalReference, IntegrationInboxItem, Organization, TimeEntry, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Inbox-Persistenz der Zeit-Import-Dienste: Idempotenz über die
 * entry-ExternalReference, offene Items der universellen Zuordnungs-Inbox,
 * Auflösung und Buchungs-Benutzer. Gemeinsam für die
 * {@see MatchingTimeImportService}-Familie, OpenProject und RemoteSupport —
 * ohne die Fuzzy-Matching-Hälfte der Basis zu erben.
 */
trait PersistsTimeImportInbox {
    /** Plugin-Id, unter der References/Inbox-Items abgelegt werden. */
    abstract protected function pluginId(): string;

    /** external_type des Idempotenz-Ankers (RemoteSupport: session). */
    protected function entryExternalType(): string {
        return 'entry';
    }

    protected function alreadyImported(Organization $organization, string $entryKey): bool {
        // Aliasse zählen mit: Zweit-Sessions am selben Zeiteintrag liegen dort
        // (extref_unique erlaubt nur eine Primär-Referenz je Ziel).
        return ExternalReference::query()
            ->forPlugin($organization, $this->pluginId(), $this->entryExternalType())
            ->forExternalId($entryKey)
            ->exists()
            || \App\Models\ExternalReferenceAlias::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', $this->pluginId())
                ->where('external_type', $this->entryExternalType())
                ->where('external_id', $entryKey)
                ->exists();
    }

    /**
     * Legt ein offenes Inbox-Item an (idempotent über den dedupe_key);
     * $attributes trägt die plugin-spezifischen Felder (source, group_key,
     * remote_snapshot, display_*, occurred_at).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function recordPendingItem(Organization $organization, string $entryKey, array $attributes): void {
        $dedupeKey = $this->entryExternalType() . ':' . $entryKey;

        $exists = IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('dedupe_key', $dedupeKey)
            ->exists();
        if ($exists) {
            return;
        }

        IntegrationInboxItem::query()->create($attributes + [
            'organization_id' => $organization->id,
            'plugin_id' => $this->pluginId(),
            'target_type' => (new TimeEntry)->getMorphClass(),
            'external_type' => $this->entryExternalType(),
            'external_id' => $entryKey,
            'dedupe_key' => $dedupeKey,
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'status' => IntegrationInboxItem::STATUS_OPEN,
        ]);
    }

    /**
     * @return Collection<int, IntegrationInboxItem>
     */
    protected function openInboxItems(Organization $organization): Collection {
        return IntegrationInboxItem::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', $this->pluginId())
            ->where('status', IntegrationInboxItem::STATUS_OPEN)
            ->whereNotNull('group_key')
            ->orderByDesc('occurred_at')
            ->get();
    }

    protected function resolveItem(IntegrationInboxItem $item, string $status, ?TimeEntry $timeEntry): void {
        $item->update([
            'status' => $status,
            'resolved_to_type' => $timeEntry?->getMorphClass(),
            'resolved_to_id' => $timeEntry?->getKey(),
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);
    }

    /**
     * Bestimmt den Buchungs-Benutzer: konfigurierte default_user_id (in der Org)
     * → Org-Owner → erster Org-Benutzer.
     */
    protected function resolveBookingUserId(Organization $organization, ?int $defaultUserId): ?int {
        if ($defaultUserId !== null) {
            $user = User::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->whereKey($defaultUserId)->first();
            if ($user !== null) {
                return (int) $user->id;
            }
        }

        if ($organization->owner_id !== null) {
            return (int) $organization->owner_id;
        }

        $first = User::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->orderBy('id')->first();

        return $first !== null ? (int) $first->id : null;
    }
}
