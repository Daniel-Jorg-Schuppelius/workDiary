<?php
/*
 * Created on   : Mon Oct 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ContactNumberAdopter.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Stammdaten;

use App\Enums\Integration\IntegrationInboxStatus;
use App\Enums\Numbering\ContactNumberOutcome;
use App\Models\Customer\Customer;
use App\Models\Integration\{ExternalReference, IntegrationInboxItem};
use App\Models\Supplier\Supplier;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Übernimmt die Kunden- bzw. Lieferantennummer eines Buchhaltungsprogramms
 * (MVP-1131/1132). Einzige Stelle, die eine Programmnummer an einen Kontakt
 * schreibt: Übertragen, Belegübergabe und Kontaktabgleich melden hierher.
 *
 * Gehört die Nummer schon einem anderen Kontakt, bleibt die bisherige Nummer
 * und die Zuordnungs-Inbox bekommt einen Feld-Konflikt — der Abgleich läuft
 * weiter, statt an der Unique-Verletzung abzubrechen.
 */
final class ContactNumberAdopter {
    private const DEDUPE_PREFIX = 'number-taken:';

    /**
     * Nach dem Übertragen: Kontakt-Referenz mit ID und Programmnummer
     * schreiben, dann die Nummer übernehmen, wenn das Programm sie vergibt.
     */
    public function linked(Customer|Supplier $party, string $pluginId, string $externalId, ?string $number): ContactNumberOutcome {
        $number = trim((string) $number);
        $values = [
            'organization_id' => $party->organization_id,
            'external_id' => $externalId,
            'synced_at' => now(),
        ];
        if ($number !== '') {
            $values['external_number'] = $number;
        }

        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => $pluginId,
                'external_type' => ExternalReference::TYPE_CONTACT,
                'referenceable_type' => $party->getMorphClass(),
                'referenceable_id' => $party->getKey(),
            ],
            $values,
        );

        return $this->adopt($party, $pluginId, $number);
    }

    /** Setzt die Programmnummer, wenn der Kontakt seine Nummer von diesem Programm bekommt. */
    public function adopt(Customer|Supplier $party, string $pluginId, ?string $number): ContactNumberOutcome {
        $number = trim((string) $number);
        if ($number === '' || (string) $party->number_source !== $pluginId) {
            return ContactNumberOutcome::Skipped;
        }

        if ((string) $party->number === $number) {
            $this->closeConflict($party, $pluginId);

            return ContactNumberOutcome::Unchanged;
        }

        $holder = $this->holder($party, $number);
        if ($holder === null) {
            $previous = $party->number;
            $party->number = $number;
            try {
                $party->save();
                $this->closeConflict($party, $pluginId);

                return ContactNumberOutcome::Adopted;
            } catch (UniqueConstraintViolationException) {
                // Zwischen Prüfung und Speichern vergeben (paralleler Lauf).
                $party->number = $previous;
                $holder = $this->holder($party, $number);
            }
        }

        $this->recordConflict($party, $pluginId, $number, $holder);

        return ContactNumberOutcome::Taken;
    }

    /** Anderer Kontakt derselben Art und Organisation mit dieser Nummer. */
    public function holder(Customer|Supplier $party, string $number): Customer|Supplier|null {
        // Ohne Global Scope, weil Abgleich und Nachlauf ohne Org-Kontext laufen; der Org-Filter ist explizit.
        /** @var Customer|Supplier|null $holder */
        $holder = $party::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $party->organization_id)
            ->where('number', $number)
            ->whereKeyNot($party->getKey())
            ->first();

        return $holder;
    }

    /** Offener Fall „Nummer schon vergeben“ zu diesem Kontakt? */
    public function hasOpenConflict(Customer|Supplier $party, string $pluginId): bool {
        return IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $party->organization_id)
            ->where('plugin_id', $pluginId)
            ->where('dedupe_key', $this->dedupeKey($party))
            ->where('status', IntegrationInboxStatus::Open)
            ->exists();
    }

    private function recordConflict(Customer|Supplier $party, string $pluginId, string $number, Customer|Supplier|null $holder): void {
        // Wie die übrigen Abgleichsfälle: ein geschlossener Fall wird nicht wieder geöffnet.
        /** @var IntegrationInboxItem $item */
        $item = IntegrationInboxItem::query()->withoutGlobalScopes()->firstOrNew(
            [
                'organization_id' => $party->organization_id,
                'plugin_id' => $pluginId,
                'dedupe_key' => $this->dedupeKey($party),
            ],
            ['status' => IntegrationInboxStatus::Open],
        );
        $item->fill([
            'source' => 'api',
            'target_type' => $party->getMorphClass(),
            'external_type' => ExternalReference::TYPE_CONTACT,
            'external_id' => ExternalReference::query()
                ->forPlugin($party->organization_id, $pluginId, ExternalReference::TYPE_CONTACT)
                ->forReferenceable($party)
                ->value('external_id'),
            'case_type' => IntegrationInboxItem::CASE_CONFLICT,
            'referenceable_type' => $party->getMorphClass(),
            'referenceable_id' => $party->getKey(),
            'remote_snapshot' => [
                'reason' => IntegrationInboxItem::REASON_NUMBER_TAKEN,
                'number' => $number,
                'holder' => $holder?->displayLabel(),
            ],
            'mapped_snapshot' => ['number' => $number],
            'local_snapshot' => ['number' => (string) $party->number],
            'diff_fields' => ['number'],
            'display_title' => null,
            'display_subtitle' => $party->displayLabel(),
        ]);
        $item->save();
    }

    private function closeConflict(Customer|Supplier $party, string $pluginId): void {
        IntegrationInboxItem::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $party->organization_id)
            ->where('plugin_id', $pluginId)
            ->where('dedupe_key', $this->dedupeKey($party))
            ->where('status', IntegrationInboxStatus::Open)
            ->update([
                'status' => IntegrationInboxStatus::ResolvedRemote,
                'resolved_to_type' => $party->getMorphClass(),
                'resolved_to_id' => $party->getKey(),
                'resolved_at' => now(),
            ]);
    }

    private function dedupeKey(Customer|Supplier $party): string {
        return self::DEDUPE_PREFIX . $party->getMorphClass() . ':' . $party->getKey();
    }
}
