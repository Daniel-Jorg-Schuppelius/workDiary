<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrationResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration;

use App\Enums\Integration\{ConflictFieldPolicy, ImportMatchPolicy};
use App\Models\{ExternalReference, ExternalReferenceAlias, IntegrationInboxItem, Organization};
use App\Services\Integration\Match\{EntityMatcher, MatchProfile};
use CommonToolkit\Enums\HashAlgorithm;
use CommonToolkit\Helper\Data\{CryptoHelper, JsonHelper};
use Illuminate\Database\Eloquent\Model;

/**
 * Der eine Engpass, durch den JEDER Import läuft (CSV-Wizard wie Plugin-Sync).
 * Erzwingt die Inbox-First-Grundregel: ein Remote-Datensatz wird nur dann
 * automatisch verlinkt, wenn er eindeutig zuzuordnen ist; ansonsten entsteht ein
 * Eintrag in der universellen Zuordnungs-Inbox — niemals blind anlegen.
 *
 * Reihenfolge (siehe ../WorkDiary-Architecture/features/053):
 *   1. ExternalReference(external_id) vorhanden → LINK (+ ggf. Conflict-Item)
 *   2. eindeutiger Match → LINK + ExternalReference
 *   3. mehrere/unsichere Kandidaten → AMBIGUOUS-Inbox-Item
 *   4. kein Kandidat & Policy=AutoLinkAndCreate → CREATE + ExternalReference
 *   5. sonst → UNMATCHED-Inbox-Item
 */
class IntegrationResolver {
    public function __construct(private readonly EntityMatcher $matcher) {}

    /**
     * @param  string  $pluginId       Quelle: toggl | lexoffice | … | csv-import
     * @param  string  $externalType   client | contact | project | entry | …
     * @param  string|null  $externalId Fremd-ID (null = z. B. CSV ohne stabile ID)
     * @param  array<string, mixed>  $attributes  ins lokale Schema gemappter Wertesatz
     * @param  array<string, mixed>  $rawRemote   Original-Payload (Inbox-Snapshot)
     */
    public function resolve(
        Organization $organization,
        string $pluginId,
        MatchProfile $profile,
        string $externalType,
        ?string $externalId,
        array $attributes,
        array $rawRemote,
        ImportMatchPolicy $policy = ImportMatchPolicy::AutoLinkExactOnly,
        ConflictFieldPolicy $onConflict = ConflictFieldPolicy::ManualReview,
        ?string $source = null,
    ): ResolveOutcome {
        $modelClass = $profile->targetType();
        $morph = (new $modelClass)->getMorphClass();
        $externalId = ($externalId === '' ? null : $externalId);

        // 1. Bereits über ExternalReference verknüpft?
        if ($externalId !== null) {
            $linked = $this->findLinked($organization, $pluginId, $externalType, $morph, $externalId);
            if ($linked instanceof Model) {
                return $this->handleLinked($organization, $profile, $linked, $attributes, $rawRemote,
                    $pluginId, $externalType, $externalId, $morph, $onConflict, $source);
            }
        }

        // 2.–3. Lokalen Match suchen
        $result = $this->matcher->match($organization, $profile, $attributes);

        if ($policy !== ImportMatchPolicy::ManualReview) {
            $exact = $result->uniqueExact();
            if ($exact instanceof Model) {
                $this->writeReference($organization, $pluginId, $externalType, $morph, $externalId, $exact, $rawRemote);

                return ResolveOutcome::linked($exact);
            }
        }

        if ($result->needsHuman() || ($policy === ImportMatchPolicy::ManualReview && ! $result->isEmpty())) {
            return ResolveOutcome::ambiguous($this->stageItem(
                $organization, $pluginId, $source, $profile, $externalType, $externalId,
                $attributes, $rawRemote, IntegrationInboxItem::CASE_AMBIGUOUS, $result->candidates(),
            ));
        }

        // 4. Anlegen nur als bewusstes Opt-in
        if ($policy === ImportMatchPolicy::AutoLinkAndCreate) {
            $created = $profile->create($organization, $attributes);
            $this->writeReference($organization, $pluginId, $externalType, $morph, $externalId, $created, $rawRemote);

            return ResolveOutcome::created($created);
        }

        // 5. Nicht zuordenbar → Inbox
        return ResolveOutcome::staged($this->stageItem(
            $organization, $pluginId, $source, $profile, $externalType, $externalId,
            $attributes, $rawRemote, IntegrationInboxItem::CASE_UNMATCHED, [],
        ));
    }

    private function findLinked(Organization $org, string $pluginId, string $externalType, string $morph, string $externalId): ?Model {
        $ref = ExternalReference::query()
            ->forPlugin($org, $pluginId, $externalType)
            ->where('referenceable_type', $morph)
            ->forExternalId($externalId)
            ->first();

        if ($ref?->referenceable instanceof Model) {
            return $ref->referenceable;
        }

        // Alias-Fallback: per Merge umgeleitete Fremd-ID direkt aufs heutige Ziel.
        $alias = ExternalReferenceAlias::resolveModel($org->id, $pluginId, $externalType, $externalId);

        return $alias instanceof Model && $alias->getMorphClass() === $morph ? $alias : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $rawRemote
     */
    private function handleLinked(
        Organization $org, MatchProfile $profile, Model $model, array $attributes, array $rawRemote,
        string $pluginId, string $externalType, string $externalId, string $morph,
        ConflictFieldPolicy $onConflict, ?string $source,
    ): ResolveOutcome {
        $diff = $this->diffFields($model, $attributes);

        if ($diff === []) {
            $this->writeReference($org, $pluginId, $externalType, $morph, $externalId, $model, $rawRemote);

            return ResolveOutcome::linked($model);
        }

        if ($onConflict === ConflictFieldPolicy::RemoteWins) {
            $model->fill($this->onlyKeys($attributes, $diff))->save();
            $this->writeReference($org, $pluginId, $externalType, $morph, $externalId, $model, $rawRemote);

            return ResolveOutcome::linked($model);
        }

        if ($onConflict === ConflictFieldPolicy::LocalWins) {
            $this->writeReference($org, $pluginId, $externalType, $morph, $externalId, $model, $rawRemote);

            return ResolveOutcome::linked($model);
        }

        // ManualReview → conflict-Inbox-Item
        $item = $this->stageItem(
            $org, $pluginId, $source, $profile, $externalType, $externalId,
            $attributes, $rawRemote, IntegrationInboxItem::CASE_CONFLICT, [],
            referenceable: $model,
            localSnapshot: $this->onlyKeys($model->attributesToArray(), $diff),
            diffFields: $diff,
        );
        $this->writeReference($org, $pluginId, $externalType, $morph, $externalId, $model, $rawRemote);

        return ResolveOutcome::conflict($item);
    }

    /**
     * Felder, in denen sich der lokale Datensatz vom gemappten Remote-Satz
     * unterscheidet (nur nicht-leere Remote-Werte zählen).
     *
     * @param  array<string, mixed>  $attributes
     * @return list<string>
     */
    private function diffFields(Model $model, array $attributes): array {
        $fillable = $model->getFillable();
        $diff = [];
        foreach ($attributes as $field => $value) {
            if (! in_array($field, $fillable, true)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            if ((string) $model->getAttribute($field) !== (string) $value) {
                $diff[] = $field;
            }
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $rawRemote
     */
    private function writeReference(
        Organization $org, string $pluginId, string $externalType, string $morph,
        ?string $externalId, Model $model, array $rawRemote,
    ): void {
        if ($externalId === null) {
            return; // ohne stabile Fremd-ID keine dauerhafte Bindung
        }

        ExternalReference::query()->updateOrCreate(
            [
                'plugin_id' => $pluginId,
                'external_type' => $externalType,
                'referenceable_type' => $morph,
                'referenceable_id' => $model->getKey(),
            ],
            [
                'organization_id' => $org->id,
                'external_id' => $externalId,
                'payload' => $rawRemote,
                'synced_at' => now(),
            ],
        );
    }

    /**
     * Idempotentes Schreiben eines Inbox-Items. Bereits aufgelöste/verworfene
     * Items werden NICHT reaktiviert (nur Snapshot/Kandidaten aktualisiert).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $rawRemote
     * @param  list<array{model: Model, confidence: string, reasons: list<string>}>  $candidates
     * @param  array<string, mixed>|null  $localSnapshot
     * @param  list<string>  $diffFields
     */
    private function stageItem(
        Organization $org, string $pluginId, ?string $source, MatchProfile $profile,
        string $externalType, ?string $externalId, array $attributes, array $rawRemote,
        string $caseType, array $candidates,
        ?Model $referenceable = null, ?array $localSnapshot = null, array $diffFields = [],
    ): IntegrationInboxItem {
        $modelClass = $profile->targetType();
        $targetType = (new $modelClass)->getMorphClass();
        $dedupeKey = $externalId !== null
            ? $externalType . ':' . $externalId
            : 'hash:' . CryptoHelper::hash(JsonHelper::encode($attributes), HashAlgorithm::SHA1);

        $display = $profile->display($attributes);
        $mainCandidate = $referenceable ?? ($candidates[0]['model'] ?? null);

        /** @var IntegrationInboxItem $item */
        $item = IntegrationInboxItem::query()->firstOrNew([
            'organization_id' => $org->id,
            'plugin_id' => $pluginId,
            'dedupe_key' => $dedupeKey,
        ]);

        if (! $item->exists) {
            $item->status = IntegrationInboxItem::STATUS_OPEN;
        }

        $item->fill([
            'source' => $source,
            'target_type' => $targetType,
            'external_type' => $externalType,
            'external_id' => $externalId,
            'case_type' => $caseType,
            'referenceable_type' => $mainCandidate?->getMorphClass(),
            'referenceable_id' => $mainCandidate?->getKey(),
            'candidate_ids' => $this->candidatePayload($candidates),
            'remote_snapshot' => $rawRemote !== [] ? $rawRemote : $attributes,
            'mapped_snapshot' => $attributes,
            'local_snapshot' => $localSnapshot,
            'diff_fields' => $diffFields !== [] ? $diffFields : null,
            'display_title' => $display['title'],
            'display_subtitle' => $display['subtitle'],
        ]);
        $item->save();

        return $item;
    }

    /**
     * @param  list<array{model: Model, confidence: string, reasons: list<string>}>  $candidates
     * @return list<array{id: int, sqid: string, label: string, confidence: string, reasons: list<string>}>|null
     */
    private function candidatePayload(array $candidates): ?array {
        if ($candidates === []) {
            return null;
        }

        return array_map(static function (array $c): array {
            $model = $c['model'];

            return [
                'id' => (int) $model->getKey(),
                'sqid' => $model->getRouteKey(),
                'label' => (string) ($model->getAttribute('name') ?? ('#' . $model->getKey())),
                'confidence' => $c['confidence'],
                'reasons' => $c['reasons'],
            ];
        }, $candidates);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function onlyKeys(array $values, array $keys): array {
        return array_intersect_key($values, array_flip($keys));
    }
}
