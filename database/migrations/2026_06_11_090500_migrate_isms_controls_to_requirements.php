<?php
/*
 * Created on   : Thu Jun 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : 2026_06_11_090500_migrate_isms_controls_to_requirements.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Datenmigration Feature 044 → 046 (gemeinsamer Managementsystem-Kern):
 * überführt die SoA-Ebene von isms_controls (code/source/applicable/
 * justification) in das neue Zielmodell:
 *
 * - Je Organisation mit ISMS-Daten wird der Default-Scope
 *   („Gesamtorganisation", is_default = true) angelegt.
 * - source=iso27001AnnexA → Requirement (norm 'ISO/IEC 27001', edition
 *   '2022', ref_no = code, source 'catalog') + ApplicabilityStatement im
 *   Default-Scope (applicable/justification/implementation_status/
 *   evidence_note übernommen). Die Control-Zeile bleibt nur dann als
 *   normneutrale Maßnahme bestehen (+ Mapping auf das Requirement), wenn
 *   sie fachlichen Eigenanteil trägt (owner_user_id gesetzt ODER Risiken
 *   verknüpft). Andernfalls wird sie HART gelöscht: ihr Inhalt ist
 *   vollständig in Requirement + Statement überführt, und ein
 *   Soft-Delete-Husk würde nach dem Spalten-Drop (2026_06_11_090600) nur
 *   einen kontextlosen Titel tragen und withTrashed-Abfragen verfälschen.
 * - source=custom → Requirement (norm 'Eigene', edition '-', source
 *   'custom') + Statement; die Control-Zeile bleibt immer als Maßnahme
 *   bestehen (+ Mapping).
 * - isms_risks.isms_scope_id wird auf den Default-Scope gebackfillt.
 *
 * Idempotent/defensiv: läuft auf leerer DB durch; ohne Alt-Spalten (z. B.
 * nach dem Spalten-Drop) ist sie ein No-Op; vorhandene Requirements/
 * Statements/Mappings werden NIE überschrieben.
 */
return new class extends Migration {
    private const DEFAULT_SCOPE_NAME = 'Gesamtorganisation';

    public function up(): void {
        if (! Schema::hasTable('isms_controls') || ! Schema::hasColumn('isms_controls', 'source')) {
            return; // Alt-Schema nicht (mehr) vorhanden — nichts zu überführen.
        }

        DB::transaction(function (): void {
            foreach ($this->organizationIds() as $organizationId) {
                $scopeId = $this->ensureDefaultScope($organizationId);

                // Risiken ohne Scope auf den Default-Scope backfillen.
                DB::table('isms_risks')
                    ->where('organization_id', $organizationId)
                    ->whereNull('isms_scope_id')
                    ->update(['isms_scope_id' => $scopeId]);

                $controls = DB::table('isms_controls')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->get();

                foreach ($controls as $control) {
                    $isCatalog = $control->source === 'iso27001AnnexA';

                    $requirementId = $this->ensureRequirement($organizationId, [
                        'norm' => $isCatalog ? 'ISO/IEC 27001' : 'Eigene',
                        'edition' => $isCatalog ? '2022' : '-',
                        'ref_no' => (string) $control->code,
                        'title' => (string) $control->title,
                        'source' => $isCatalog ? 'catalog' : 'custom',
                    ]);

                    $this->ensureStatement($organizationId, $scopeId, $requirementId, $control);

                    $keepAsMeasure = ! $isCatalog
                        || $control->owner_user_id !== null
                        || DB::table('isms_control_risk')->where('control_id', $control->id)->exists();

                    if ($keepAsMeasure) {
                        $this->ensureMapping((int) $control->id, $requirementId);
                    } else {
                        // Reiner Katalogeintrag ohne Eigenanteil: vollständig
                        // durch Requirement + Statement ersetzt — hart löschen.
                        DB::table('isms_controls')->where('id', $control->id)->delete();
                    }
                }
            }
        });
    }

    public function down(): void {
        // Datenmigration ist nicht verlustfrei umkehrbar (gelöschte
        // Katalog-Controls). Bewusst No-Op — Rollback erfolgt über Backup.
    }

    /**
     * Organisationen mit ISMS-Bestand (Controls ODER Risiken ohne Scope).
     *
     * @return list<int>
     */
    private function organizationIds(): array {
        $fromControls = DB::table('isms_controls')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('organization_id');

        $fromRisks = DB::table('isms_risks')
            ->whereNull('deleted_at')
            ->whereNull('isms_scope_id')
            ->distinct()
            ->pluck('organization_id');

        return $fromControls->merge($fromRisks)->map(intval(...))->unique()->values()->all();
    }

    private function ensureDefaultScope(int $organizationId): int {
        $existing = DB::table('isms_scopes')
            ->where('organization_id', $organizationId)
            ->where('is_default', true)
            ->whereNull('deleted_at')
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('isms_scopes')->insertGetId([
            'organization_id' => $organizationId,
            'name' => self::DEFAULT_SCOPE_NAME,
            'description' => null,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param  array{norm: string, edition: string, ref_no: string, title: string, source: string}  $attributes */
    private function ensureRequirement(int $organizationId, array $attributes): int {
        $existing = DB::table('isms_requirements')
            ->where('organization_id', $organizationId)
            ->where('norm', $attributes['norm'])
            ->where('edition', $attributes['edition'])
            ->where('ref_no', $attributes['ref_no'])
            ->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('isms_requirements')->insertGetId([
            'organization_id' => $organizationId,
            ...$attributes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureStatement(int $organizationId, int $scopeId, int $requirementId, object $control): void {
        $exists = DB::table('isms_applicability_statements')
            ->where('isms_scope_id', $scopeId)
            ->where('isms_requirement_id', $requirementId)
            ->exists();

        if ($exists) {
            return; // Gepflegte Statements nie überschreiben.
        }

        DB::table('isms_applicability_statements')->insert([
            'organization_id' => $organizationId,
            'isms_scope_id' => $scopeId,
            'isms_requirement_id' => $requirementId,
            'applicable' => (bool) $control->applicable,
            'justification' => $control->justification,
            'implementation_status' => (string) $control->implementation_status,
            'evidence_note' => $control->evidence_note,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function ensureMapping(int $controlId, int $requirementId): void {
        $exists = DB::table('isms_control_requirement')
            ->where('control_id', $controlId)
            ->where('requirement_id', $requirementId)
            ->exists();

        if (! $exists) {
            DB::table('isms_control_requirement')->insert([
                'control_id' => $controlId,
                'requirement_id' => $requirementId,
            ]);
        }
    }
};
