<?php
/*
 * Created on   : Tue Jun 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DedupsAndStages.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Import\Specs\Concerns;

use App\Models\{ExternalReference, IntegrationInboxItem, Organization};
use App\Services\Import\ImportOutcome;
use App\Services\Integration\Match\{EntityMatcher, MatchProfile};
use Illuminate\Database\Eloquent\Model;

/**
 * Gemeinsame Dedup-/Staging-Logik für CSV-Specs mit einem MatchProfile
 * (Customer/Supplier/Article).
 *
 * Auflösungs-Reihenfolge in {@see resolveImport()}:
 *  1. Fremd-ID (optionale Spalte `external_id`) → bestehende
 *     {@see ExternalReference}-Bindung (reimport-fest, auch bei geänderter
 *     Nummer/Name).
 *  2. Exakter Treffer über die Nummer → vollständiges Update.
 *  3. Eindeutiger EXACT-Treffer über den {@see EntityMatcher}
 *     (z. B. USt-IdNr./GTIN) → bestehende Nummer bleibt erhalten.
 *  4. Sonst: anlegen (auto_create) bzw. in die Zuordnungs-Inbox (inbox_first).
 *
 * Plugin-Kennung für CSV-Bindungen/-Inbox.
 */
trait DedupsAndStages {
    private const CSV_PLUGIN = IntegrationInboxItem::PLUGIN_CSV;

    /**
     * Orchestriert die Zeilen-Auflösung (gemeinsam für upsert/upsertOrStage).
     *
     * @param  array<string, mixed>  $payload  ins lokale Schema gemappter Zeilensatz
     * @param  string  $externalType  Entitätswert (customers|suppliers|articles)
     * @return array{0: ImportOutcome, 1: null}
     */
    protected function resolveImport(Organization $organization, array $payload, MatchProfile $profile, string $externalType, bool $inboxFirst): array {
        $modelClass = $profile->targetType();
        $externalId = $this->pullExternalId($payload);

        // 1. Bestehende Fremd-ID-Bindung → reimport-fest aktualisieren.
        if ($externalId !== null) {
            $linked = $this->resolveByExternalId($organization, $externalId, $modelClass, $externalType);
            if ($linked instanceof Model) {
                $this->applyMatch($linked, $payload, true);

                return [ImportOutcome::Updated, null];
            }
        }

        // 2./3. Nummer- bzw. feldübergreifender EXACT-Treffer.
        [$match, $byNumber] = $this->dedupMatch($organization, $payload, $profile);
        if ($match instanceof Model) {
            $this->applyMatch($match, $payload, $byNumber);
            if ($externalId !== null) {
                $this->writeExternalRef($organization, $match, $externalId, $externalType);
            }

            return [ImportOutcome::Updated, null];
        }

        // 4. Anlegen oder in die Inbox.
        if ($inboxFirst) {
            $this->stageToInbox($organization, $payload, $profile, $externalType, $externalId);

            return [ImportOutcome::Skipped, null];
        }

        /** @var Model $created */
        $created = $modelClass::create($payload);
        if ($externalId !== null) {
            $this->writeExternalRef($organization, $created, $externalId, $externalType);
        }

        return [ImportOutcome::Created, null];
    }

    /**
     * Findet den eindeutigen Bestandsdatensatz für eine Zeile (Nummer → Matcher).
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: ?Model, 1: bool}  [Treffer, true wenn über die Nummer]
     */
    protected function dedupMatch(Organization $organization, array $payload, MatchProfile $profile): array {
        $modelClass = $profile->targetType();

        $number = $payload['number'] ?? null;
        if ($number !== null && $number !== '') {
            $byNumber = $modelClass::query()
                ->where('organization_id', $organization->id)
                ->where('number', $number)
                ->first();
            if ($byNumber instanceof Model) {
                return [$byNumber, true];
            }
        }

        $matched = app(EntityMatcher::class)->match($organization, $profile, $payload)->uniqueExact();

        return [$matched, false];
    }

    /**
     * Wendet die Zeilenwerte auf den Treffer an. Bei Nicht-Nummer-Treffern bleibt
     * die bestehende Nummer erhalten.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function applyMatch(Model $model, array $payload, bool $matchedByNumber): void {
        $fields = $payload;
        if (! $matchedByNumber) {
            unset($fields['number']);
        }
        $model->fill($fields)->save();
    }

    /**
     * Legt für eine unzuordenbare Zeile einen Eintrag in der universellen
     * Zuordnungs-Inbox an. Idempotenz: stabile Fremd-ID, sonst Hash der Zeile.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function stageToInbox(Organization $organization, array $payload, MatchProfile $profile, string $externalType, ?string $externalId = null): void {
        $modelClass = $profile->targetType();
        $targetType = (new $modelClass)->getMorphClass();
        $dedupeKey = $externalId !== null
            ? $externalType . ':' . $externalId
            : 'hash:' . sha1((string) json_encode($payload));
        $display = $profile->display($payload);

        /** @var IntegrationInboxItem $item */
        $item = IntegrationInboxItem::query()->firstOrNew([
            'organization_id' => $organization->id,
            'plugin_id' => self::CSV_PLUGIN,
            'dedupe_key' => $dedupeKey,
        ]);
        if (! $item->exists) {
            $item->status = IntegrationInboxItem::STATUS_OPEN;
        }
        $item->fill([
            'source' => 'csv',
            'target_type' => $targetType,
            'external_type' => $externalType,
            'external_id' => $externalId,
            'case_type' => IntegrationInboxItem::CASE_UNMATCHED,
            'remote_snapshot' => $payload,
            'mapped_snapshot' => $payload,
            'display_title' => $display['title'],
            'display_subtitle' => $display['subtitle'],
        ])->save();
    }

    /**
     * Zieht die optionale Fremd-ID aus dem Zeilensatz (und entfernt sie, damit
     * sie nicht als Modellfeld behandelt wird).
     *
     * @param  array<string, mixed>  $payload
     */
    private function pullExternalId(array &$payload): ?string {
        $value = $payload['external_id'] ?? null;
        unset($payload['external_id']);
        $value = is_string($value) ? trim($value) : '';

        return $value !== '' ? $value : null;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function resolveByExternalId(Organization $organization, string $externalId, string $modelClass, string $externalType): ?Model {
        $morph = (new $modelClass)->getMorphClass();
        $ref = ExternalReference::query()
            ->where('organization_id', $organization->id)
            ->where('plugin_id', self::CSV_PLUGIN)
            ->where('external_type', $externalType)
            ->where('referenceable_type', $morph)
            ->where('external_id', $externalId)
            ->first();

        return $ref?->referenceable;
    }

    private function writeExternalRef(Organization $organization, Model $model, string $externalId, string $externalType): void {
        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => self::CSV_PLUGIN,
                'external_type' => $externalType,
                'referenceable_type' => $model->getMorphClass(),
                'referenceable_id' => $model->getKey(),
            ],
            [
                'organization_id' => $organization->id,
                'external_id' => $externalId,
                'synced_at' => now(),
            ],
        );
    }
}
