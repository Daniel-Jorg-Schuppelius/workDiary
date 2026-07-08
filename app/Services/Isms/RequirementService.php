<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RequirementService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Isms;

use App\Enums\Isms\{ControlImplementationStatus, RequirementSource};
use App\Models\Isms\{IsmsApplicabilityStatement, IsmsRequirement, IsmsScope};
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domain-Service Normanforderungen + SoA-Aussagen (Feature 044/046).
 *
 * Katalog-Import (Normprofile, config/isms-norms/ via
 * {@see NormProfileRegistry}): erzeugt Requirements (norm/edition aus dem
 * Profil, source catalog) UND fehlende Statements für den GEWÄHLTEN Scope
 * (Default: Default-Scope). Idempotent per org+norm+edition+ref_no —
 * bestehende Requirements und gepflegte Statements werden NIE
 * überschrieben (auch soft-gelöschte Requirements werden nicht neu
 * angelegt, der Unique-Index deckt sie mit ab).
 *
 * SoA-Regel (zentral hier durchgesetzt, zusätzlich zur Request-Validierung):
 * - applicable = false ⇒ justification ist PFLICHT und
 *   implementation_status wird auf notApplicable gesetzt.
 * - applicable = true  ⇒ ein Status notApplicable wird auf open
 *   zurückgesetzt (sonst widerspräche die SoA-Aussage sich selbst).
 */
class RequirementService {
    public function __construct(
        private readonly ScopeService $scopes,
        private readonly NormProfileRegistry $registry,
    ) {}

    /**
     * Lädt den Referenzkatalog eines Normprofils (nur Ref-Nr. + Kurztitel —
     * KEINE Normtexte) in die Organisation des Akteurs und legt fehlende
     * SoA-Statements für den gewählten Scope an (Default: Default-Scope).
     *
     * @return int Anzahl neu angelegter Anforderungen
     *
     * @throws \InvalidArgumentException bei unbekanntem Profil-Key
     */
    public function importCatalog(User $actor, string $profileKey, ?IsmsScope $scope = null): int {
        $profile = $this->registry->get($profileKey);
        $entries = $this->registry->requirements($profileKey);

        return DB::transaction(function () use ($actor, $profile, $entries, $scope): int {
            $scope ??= $this->scopes->ensureDefaultScope((int) $actor->organization_id);

            $existing = IsmsRequirement::query()
                ->withTrashed()
                ->where('norm', $profile['norm'])
                ->where('edition', $profile['edition'])
                ->pluck('id', 'ref_no');

            $created = 0;
            foreach ($entries as $entry) {
                $refNo = $entry['ref_no'];

                if (isset($existing[$refNo])) {
                    $requirementId = (int) $existing[$refNo];
                } else {
                    $requirement = IsmsRequirement::query()->create([
                        'organization_id' => $actor->organization_id,
                        'norm' => $profile['norm'],
                        'edition' => $profile['edition'],
                        'ref_no' => $refNo,
                        'title' => $entry['title'],
                        'source' => RequirementSource::Catalog->value,
                    ]);
                    $requirementId = (int) $requirement->id;
                    $created++;
                }

                $this->ensureStatement((int) $actor->organization_id, $scope, $requirementId);
            }

            return $created;
        });
    }

    /**
     * OSCAL-Katalog-Import (Nachtrag 044a, AR §24): liest einen OSCAL-1.x-
     * Catalog (JSON) — z. B. NIST SP 800-53/CSF (public domain) oder die
     * BSI Stand-der-Technik-Bibliothek (CC BY-SA) — und materialisiert die
     * Controls als Anforderungen MIT Volltext (description aus den
     * parts[]-Prosen). Struktur wird vor dem Import gegen ein Minimalschema
     * validiert (opis/json-schema, Draft 2020-12). Idempotent wie
     * {@see importCatalog()}: bestehende Requirements bleiben unangetastet.
     *
     * @return array{norm: string, edition: string, created: int}
     *
     * @throws ValidationException bei ungültigem JSON/Schema
     */
    public function importOscalCatalog(User $actor, string $json, ?IsmsScope $scope = null): array {
        $document = json_decode($json, true);
        if (! is_array($document)) {
            throw ValidationException::withMessages(['file' => __('Die Datei enthält kein gültiges JSON.')]);
        }

        $this->validateOscal($document);

        /** @var array<string, mixed> $catalog */
        $catalog = $document['catalog'];
        $norm = mb_substr(trim((string) data_get($catalog, 'metadata.title', 'OSCAL')), 0, 64);
        $edition = mb_substr(trim((string) data_get($catalog, 'metadata.version', '1')), 0, 16);

        $controls = [];
        $this->collectOscalControls((array) ($catalog['controls'] ?? []), $controls);
        foreach ((array) ($catalog['groups'] ?? []) as $group) {
            $this->collectOscalControls((array) data_get($group, 'controls', []), $controls);
        }
        if ($controls === []) {
            throw ValidationException::withMessages(['file' => __('Der OSCAL-Katalog enthält keine Controls.')]);
        }

        return DB::transaction(function () use ($actor, $scope, $norm, $edition, $controls): array {
            $scope ??= $this->scopes->ensureDefaultScope((int) $actor->organization_id);

            $existing = IsmsRequirement::query()
                ->withTrashed()
                ->where('norm', $norm)
                ->where('edition', $edition)
                ->pluck('id', 'ref_no');

            $created = 0;
            foreach ($controls as $control) {
                $refNo = $control['ref_no'];

                if (isset($existing[$refNo])) {
                    $requirementId = (int) $existing[$refNo];
                } else {
                    $requirement = IsmsRequirement::query()->create([
                        'organization_id' => $actor->organization_id,
                        'norm' => $norm,
                        'edition' => $edition,
                        'ref_no' => $refNo,
                        'title' => $control['title'],
                        'description' => $control['description'],
                        'source' => RequirementSource::Catalog->value,
                    ]);
                    $requirementId = (int) $requirement->id;
                    $created++;
                }

                $this->ensureStatement((int) $actor->organization_id, $scope, $requirementId);
            }

            return ['norm' => $norm, 'edition' => $edition, 'created' => $created];
        });
    }

    /**
     * Minimalschema-Validierung des OSCAL-Dokuments (Draft 2020-12).
     *
     * @param array<string, mixed> $document
     *
     * @throws ValidationException
     */
    private function validateOscal(array $document): void {
        $schema = <<<'JSON'
        {
            "$schema": "https://json-schema.org/draft/2020-12/schema",
            "type": "object",
            "required": ["catalog"],
            "properties": {
                "catalog": {
                    "type": "object",
                    "required": ["metadata"],
                    "properties": {
                        "metadata": {
                            "type": "object",
                            "required": ["title"],
                            "properties": {
                                "title": {"type": "string"},
                                "version": {"type": ["string", "number"]}
                            }
                        },
                        "groups": {"type": "array"},
                        "controls": {"type": "array"}
                    }
                }
            }
        }
        JSON;

        $validator = new \Opis\JsonSchema\Validator;
        $result = $validator->validate(
            \Opis\JsonSchema\Helper::toJSON($document),
            $schema,
        );

        if (! $result->isValid()) {
            $error = $result->error();
            throw ValidationException::withMessages([
                'file' => __('Kein gültiger OSCAL-Katalog: :error', [
                    'error' => $error !== null ? (string) json_encode((new \Opis\JsonSchema\Errors\ErrorFormatter)->format($error, false)) : 'unbekannt',
                ]),
            ]);
        }
    }

    /**
     * Sammelt Controls rekursiv (Controls können verschachtelte controls[]
     * enthalten; Gruppen ebenfalls).
     *
     * @param array<int|string, mixed> $controls
     * @param list<array{ref_no: string, title: string, description: ?string}> $collected
     */
    private function collectOscalControls(array $controls, array &$collected): void {
        foreach ($controls as $control) {
            if (! is_array($control)) {
                continue;
            }
            $id = mb_substr(trim((string) ($control['id'] ?? '')), 0, 24);
            $title = mb_substr(trim((string) ($control['title'] ?? '')), 0, 180);
            if ($id === '' || $title === '') {
                continue;
            }

            $prose = [];
            foreach ((array) ($control['parts'] ?? []) as $part) {
                if (is_array($part) && isset($part['prose']) && is_string($part['prose']) && $part['prose'] !== '') {
                    $prose[] = $part['prose'];
                }
            }

            $collected[] = [
                'ref_no' => $id,
                'title' => $title,
                'description' => $prose !== [] ? implode("\n\n", $prose) : null,
            ];

            $this->collectOscalControls((array) ($control['controls'] ?? []), $collected);
        }
    }

    /**
     * Legt fehlende SoA-Statements für einen Geltungsbereich an — für die
     * Anforderungen einer Norm (norm + edition) oder, ohne Filter, für ALLE
     * Anforderungen der Organisation. Idempotent: bestehende (gepflegte)
     * und soft-gelöschte Statements bleiben unangetastet.
     *
     * @return int Anzahl neu angelegter Statements
     */
    public function ensureStatementsForScope(IsmsScope $scope, ?string $norm = null, ?string $edition = null): int {
        return DB::transaction(function () use ($scope, $norm, $edition): int {
            $requirementIds = IsmsRequirement::query()
                ->when($norm !== null, fn($query) => $query->where('norm', $norm))
                ->when($edition !== null, fn($query) => $query->where('edition', $edition))
                ->pluck('id');

            $created = 0;
            foreach ($requirementIds as $requirementId) {
                if ($this->ensureStatement((int) $scope->organization_id, $scope, (int) $requirementId)) {
                    $created++;
                }
            }

            return $created;
        });
    }

    /**
     * Legt eine eigene Anforderung an (source custom) inkl. leerem
     * Statement im Default-Scope.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(User $creator, array $attributes): IsmsRequirement {
        return DB::transaction(function () use ($creator, $attributes): IsmsRequirement {
            $requirement = IsmsRequirement::query()->create([
                'organization_id' => $creator->organization_id,
                'norm' => trim((string) ($attributes['norm'] ?? '')) !== '' ? trim((string) $attributes['norm']) : __('isms.requirement.norm_custom'),
                'edition' => trim((string) ($attributes['edition'] ?? '')) !== '' ? trim((string) $attributes['edition']) : '-',
                'ref_no' => trim((string) $attributes['ref_no']),
                'title' => $attributes['title'],
                'source' => RequirementSource::Custom->value,
            ]);

            $scope = $this->scopes->ensureDefaultScope((int) $creator->organization_id);
            $this->ensureStatement((int) $creator->organization_id, $scope, (int) $requirement->id);

            return $requirement;
        });
    }

    /**
     * Aktualisiert eine Anforderung. Katalog-Anforderungen sind Referenz:
     * Norm/Edition/Ref-Nr. bleiben unveränderlich, nur der eigene Kurztitel
     * ist pflegbar.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(IsmsRequirement $requirement, User $actor, array $attributes): IsmsRequirement {
        unset($actor);

        return DB::transaction(function () use ($requirement, $attributes): IsmsRequirement {
            $isCatalog = $requirement->source === RequirementSource::Catalog;

            $requirement->update([
                'norm' => $isCatalog ? $requirement->norm : trim((string) ($attributes['norm'] ?? $requirement->norm)),
                'edition' => $isCatalog ? $requirement->edition : trim((string) ($attributes['edition'] ?? $requirement->edition)),
                'ref_no' => $isCatalog ? $requirement->ref_no : trim((string) ($attributes['ref_no'] ?? $requirement->ref_no)),
                'title' => $attributes['title'] ?? $requirement->title,
            ]);

            return $requirement;
        });
    }

    /**
     * Soft-Delete einer eigenen Anforderung inkl. ihrer Statements;
     * Maßnahmen-Mappings werden gelöst, die Maßnahmen bleiben bestehen.
     */
    public function delete(IsmsRequirement $requirement, User $actor): void {
        DB::transaction(function () use ($requirement, $actor): void {
            $requirement->audit('isms.requirement.deleted', ['actor_user_id' => $actor->id]);
            $requirement->controls()->detach();
            $requirement->statements()->delete();
            $requirement->delete();
        });
    }

    /**
     * Aktualisiert die SoA-Aussage eines Statements (zentrale SoA-Regel,
     * siehe Klassen-Doc).
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException wenn applicable=false ohne Begründung
     */
    public function updateStatement(IsmsApplicabilityStatement $statement, User $actor, array $attributes): IsmsApplicabilityStatement {
        unset($actor);
        $attributes = $this->applySoaRule($attributes, $statement);

        return DB::transaction(function () use ($statement, $attributes): IsmsApplicabilityStatement {
            $statement->update([
                'applicable' => array_key_exists('applicable', $attributes) ? (bool) $attributes['applicable'] : $statement->applicable,
                'justification' => array_key_exists('justification', $attributes) ? $attributes['justification'] : $statement->justification,
                'implementation_status' => $attributes['implementation_status'] ?? $statement->implementation_status,
                'evidence_note' => array_key_exists('evidence_note', $attributes) ? $attributes['evidence_note'] : $statement->evidence_note,
            ]);

            return $statement;
        });
    }

    /**
     * Fehlendes Statement (Scope + Anforderung) anlegen — bestehende
     * (gepflegte) Statements bleiben unangetastet. Soft-gelöschte
     * Statements gelten als bewusst entfernt und werden nicht reaktiviert.
     *
     * @return bool true, wenn ein Statement neu angelegt wurde
     */
    private function ensureStatement(int $organizationId, IsmsScope $scope, int $requirementId): bool {
        $exists = IsmsApplicabilityStatement::query()
            ->withTrashed()
            ->where('isms_scope_id', $scope->id)
            ->where('isms_requirement_id', $requirementId)
            ->exists();

        if ($exists) {
            return false;
        }

        IsmsApplicabilityStatement::query()->create([
            'organization_id' => $organizationId,
            'isms_scope_id' => $scope->id,
            'isms_requirement_id' => $requirementId,
            'applicable' => true,
            'justification' => null,
            'implementation_status' => ControlImplementationStatus::Open->value,
            'evidence_note' => null,
        ]);

        return true;
    }

    /**
     * Zentrale SoA-Regel (siehe Klassen-Doc).
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     *
     * @throws ValidationException wenn applicable=false ohne Begründung
     */
    private function applySoaRule(array $attributes, IsmsApplicabilityStatement $statement): array {
        $applicable = array_key_exists('applicable', $attributes)
            ? (bool) $attributes['applicable']
            : $statement->applicable;

        if (! $applicable) {
            $justification = trim((string) ($attributes['justification']
                ?? $statement->justification
                ?? ''));

            if ($justification === '') {
                throw ValidationException::withMessages([
                    'justification' => __('isms.error.justification_required'),
                ]);
            }

            $attributes['justification'] = $justification;
            $attributes['implementation_status'] = ControlImplementationStatus::NotApplicable->value;
        } elseif (($attributes['implementation_status'] ?? $statement->implementation_status->value)
            === ControlImplementationStatus::NotApplicable->value
        ) {
            $attributes['implementation_status'] = ControlImplementationStatus::Open->value;
        }

        return $attributes;
    }
}
