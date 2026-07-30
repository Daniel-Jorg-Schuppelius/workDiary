<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureTemplateService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Services\Procedure;

use App\Enums\Procedure\ProcedureRiskLevel;
use App\Exceptions\PublishedProcedureVersionLockedException;
use App\Models\{Organization, ProcedureStepDef, ProcedureTemplate, ProcedureTemplateVersion, User};
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Verwaltet Prozedurvorlagen und ihre Versionen (MVP-025).
 *
 * Die Veroeffentlichung einer Version setzt den Gueltigkeitszeitraum
 * (`valid_from`) und schliesst die zuvor aktive Version automatisch ab
 * (`valid_to = valid_from - 1 day`). Nach der Veroeffentlichung sind
 * Schrittdefinitionen unveraenderlich; Aenderungen erfordern eine
 * neue Version.
 */
class ProcedureTemplateService {
    /**
     * @param  array{code: string, name: string, description?: ?string, domain?: ?string, active?: bool}  $attributes
     */
    public function create(Organization $organization, User $author, array $attributes): ProcedureTemplate {
        return DB::transaction(function () use ($organization, $author, $attributes) {
            $template = new ProcedureTemplate([
                'code' => $attributes['code'],
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'domain' => $attributes['domain'] ?? null,
                'active' => $attributes['active'] ?? true,
            ]);
            $template->organization_id = $organization->id;
            $template->save();

            $this->addVersion($template, $author, [
                'change_note' => __('procedure.flash.versionInitial'),
            ]);

            return $template->refresh();
        });
    }

    /**
     * Legt eine neue (unveroeffentlichte) Version an. Die Nummer wird
     * automatisch hochgezaehlt.
     *
     * @param  array{change_note?: ?string, risk_level?: ProcedureRiskLevel|string, applicability?: ?array<string, mixed>}  $attributes
     */
    public function addVersion(ProcedureTemplate $template, User $author, array $attributes = []): ProcedureTemplateVersion {
        return DB::transaction(function () use ($template, $attributes) {
            $next = (int) ($template->versions()->max('version') ?? 0) + 1;
            $riskLevel = $attributes['risk_level'] ?? ProcedureRiskLevel::Normal;
            if ($riskLevel instanceof ProcedureRiskLevel) {
                $riskLevel = $riskLevel->value;
            }

            $version = new ProcedureTemplateVersion([
                'version' => $next,
                'change_note' => $attributes['change_note'] ?? null,
                'risk_level' => $riskLevel,
                'applicability' => $attributes['applicability'] ?? null,
            ]);
            $version->procedure_template_id = $template->id;
            $version->save();

            return $version->refresh();
        });
    }

    /**
     * Fuegt eine Schritt-Definition zur angegebenen (Draft-)Version
     * hinzu. Wirft auf veroeffentlichten Versionen.
     *
     * @param  array{code: string, step_type: string, label: string, sort_order?: int, description?: ?string, required?: bool, blocking?: bool, config?: ?array<string, mixed>, required_role?: ?string, required_qualification_code?: ?string, requires_second_person?: bool, requires_proof_type?: ?string}  $attributes
     */
    public function addStepDef(ProcedureTemplateVersion $version, array $attributes): ProcedureStepDef {
        if ($version->isPublished()) {
            throw PublishedProcedureVersionLockedException::forVersion($version);
        }

        return DB::transaction(function () use ($version, $attributes) {
            $sortOrder = $attributes['sort_order'] ?? ((int) ($version->steps()->max('sort_order') ?? 0) + 10);

            $step = new ProcedureStepDef([
                'sort_order' => $sortOrder,
                'code' => $attributes['code'],
                'step_type' => $attributes['step_type'],
                'label' => $attributes['label'],
                'description' => $attributes['description'] ?? null,
                'required' => $attributes['required'] ?? true,
                'blocking' => $attributes['blocking'] ?? true,
                'config' => $attributes['config'] ?? null,
                'required_role' => $attributes['required_role'] ?? null,
                'required_qualification_code' => $attributes['required_qualification_code'] ?? null,
                'requires_second_person' => $attributes['requires_second_person'] ?? false,
                'requires_proof_type' => $attributes['requires_proof_type'] ?? null,
            ]);
            $step->procedure_template_version_id = $version->id;
            $step->save();

            return $step->refresh();
        });
    }

    /**
     * Aktualisiert eine Schritt-Definition. Erlaubt nur in
     * Draft-Versionen.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateStepDef(ProcedureStepDef $step, array $attributes): ProcedureStepDef {
        $version = $step->version()->firstOrFail();
        if ($version->isPublished()) {
            throw PublishedProcedureVersionLockedException::forVersion($version);
        }
        $step->fill($attributes);
        $step->save();
        return $step;
    }

    /**
     * Loescht eine Schritt-Definition. Erlaubt nur in Draft-Versionen.
     */
    public function deleteStepDef(ProcedureStepDef $step): void {
        $version = $step->version()->firstOrFail();
        if ($version->isPublished()) {
            throw PublishedProcedureVersionLockedException::forVersion($version);
        }
        $step->delete();
    }

    /**
     * Aktualisiert die Stammdaten einer Vorlage (Name/Beschreibung/Domain)
     * sowie deren Aktiv-Status. Versionen bleiben unberuehrt.
     *
     * @param  array{name?: string, description?: ?string, domain?: ?string, active?: bool}  $attributes
     */
    public function updateTemplate(ProcedureTemplate $template, array $attributes): ProcedureTemplate {
        $template->fill(array_intersect_key($attributes, array_flip([
            'name', 'description', 'domain', 'active',
        ])));
        $template->save();
        return $template->refresh();
    }

    /**
     * Aktualisiert die Metadaten einer noch nicht veroeffentlichten
     * Version (Risikostufe, Anwendbarkeit, Aenderungsnotiz). Wirft auf
     * veroeffentlichten Versionen.
     *
     * @param  array{change_note?: ?string, risk_level?: ProcedureRiskLevel|string, applicability?: ?array<string, mixed>}  $attributes
     */
    public function updateVersion(ProcedureTemplateVersion $version, array $attributes): ProcedureTemplateVersion {
        if ($version->isPublished()) {
            throw PublishedProcedureVersionLockedException::forVersion($version);
        }

        if (array_key_exists('risk_level', $attributes)) {
            $riskLevel = $attributes['risk_level'];
            $version->risk_level = $riskLevel instanceof ProcedureRiskLevel
                ? $riskLevel
                : ProcedureRiskLevel::from((string) $riskLevel);
        }
        if (array_key_exists('change_note', $attributes)) {
            $version->change_note = $attributes['change_note'];
        }
        if (array_key_exists('applicability', $attributes)) {
            $version->applicability = $attributes['applicability'];
        }
        $version->save();
        return $version->refresh();
    }

    /**
     * Ersetzt saemtliche Schritt-Definitionen einer Draft-Version durch
     * die uebergebene Liste (Designer-Speichern). Reihenfolge ergibt sich
     * aus der Listen-Position (sort_order = (Index + 1) * 10). Wirft auf
     * veroeffentlichten Versionen.
     *
     * @param  list<array<string, mixed>>  $steps
     */
    public function syncSteps(ProcedureTemplateVersion $version, array $steps): ProcedureTemplateVersion {
        if ($version->isPublished()) {
            throw PublishedProcedureVersionLockedException::forVersion($version);
        }

        return DB::transaction(function () use ($version, $steps) {
            $version->steps()->delete();

            foreach ($steps as $index => $step) {
                $this->addStepDef($version, [
                    'sort_order' => ($index + 1) * 10,
                    'code' => $step['code'],
                    'step_type' => $step['step_type'],
                    'label' => $step['label'],
                    'description' => $step['description'] ?? null,
                    'required' => $step['required'] ?? true,
                    'blocking' => $step['blocking'] ?? true,
                    'config' => $step['config'] ?? null,
                    'required_role' => $step['required_role'] ?? null,
                    'required_qualification_code' => $step['required_qualification_code'] ?? null,
                    'requires_second_person' => $step['requires_second_person'] ?? false,
                    'requires_proof_type' => $step['requires_proof_type'] ?? null,
                ]);
            }

            return $version->refresh();
        });
    }

    /**
     * Veroeffentlicht die angegebene Version. Die vorherige aktive
     * Version (mit kleinerer Versionsnummer und ohne `valid_to`) wird
     * mit `valid_to = valid_from - 1 day` geschlossen.
     */
    public function publish(ProcedureTemplateVersion $version, User $publisher, ?CarbonInterface $validFrom = null): ProcedureTemplateVersion {
        return DB::transaction(function () use ($version, $publisher, $validFrom) {
            if ($version->isPublished()) {
                return $version;
            }

            // Partyservice-Rezepte (MVP-455): ungeklärte Allergene blockieren
            // die Freigabe; Rezepte ohne Profil sind nie betroffen. Lazy
            // aufgelöst, um keinen Konstruktor-Zyklus aufzubauen.
            app(\App\Services\Recipes\RecipeService::class)->assertPublishable($version);

            $from = ($validFrom ?? Carbon::today())->copy()->startOfDay();

            // Schliesse offene vorherige Versionen.
            ProcedureTemplateVersion::query()
                ->where('procedure_template_id', $version->procedure_template_id)
                ->where('id', '!=', $version->id)
                ->whereNotNull('published_at')
                ->whereNull('valid_to')
                ->update([
                    'valid_to' => $from->copy()->subDay()->toDateString(),
                ]);

            $version->forceFill([
                'published_at' => now(),
                'published_by_user_id' => $publisher->id,
                'valid_from' => $from->toDateString(),
                'valid_to' => null,
            ])->save();

            return $version->refresh();
        });
    }

    /**
     * Liefert die zum Stichtag gueltige veroeffentlichte Version oder
     * null.
     */
    public function currentVersionFor(ProcedureTemplate $template, ?CarbonInterface $at = null): ?ProcedureTemplateVersion {
        $date = ($at ?? Carbon::today())->toDateString();

        return ProcedureTemplateVersion::query()
            ->where('procedure_template_id', $template->id)
            ->whereNotNull('published_at')
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('version')
            ->first();
    }
}
