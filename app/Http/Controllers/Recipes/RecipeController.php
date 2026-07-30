<?php
/*
 * Created on   : Thu Jul 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RecipeController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Http\Controllers\Recipes;

use App\Enums\Classification\ClassificationDomain;
use App\Enums\Manufacturing\QuantityKind;
use App\Http\Controllers\Controller;
use App\Models\{Article, Classification, ProcedureMaterialRequirement, ProcedureTemplate, ProcedureTemplateVersion};
use App\Models\Recipes\RecipeProfile;
use App\Services\Recipes\RecipeService;
use CommonToolkit\Enums\RoundingMode;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Rezeptpflege (MVP-455) am Prozedur-Designer: branchenneutrale
 * Materialpositionen (fest / je Einheit / Verhältnis, Verschnitt, Rundung,
 * Werkzeuge) sind NUR in Draft-Versionen editierbar — veröffentlichte
 * Stände bleiben unveränderliche Snapshots. Der Partyservice-Aufsatz
 * (Grundausbeute/Portionen, Allergen-Abweichungen, Zutaten-Allergene) ist
 * nur bei installiertem Branchenprofil erreichbar.
 */
class RecipeController extends Controller {
    public function __construct(private readonly RecipeService $recipes) {}

    /** Position anlegen oder (bei mitgegebener Position) aktualisieren. */
    public function storeMaterial(Request $request, ProcedureTemplate $template, ProcedureTemplateVersion $version): RedirectResponse {
        Gate::authorize('update', $template);
        $this->assertDraft($template, $version);

        $data = $request->validate([
            'requirement' => ['nullable', 'string'],
            'article' => ['required', 'string'],
            'quantity_kind' => ['required', Rule::enum(QuantityKind::class)],
            'quantity' => ['nullable', 'numeric', 'min:0', 'required_unless:quantity_kind,ratio'],
            'ratio_part' => ['nullable', 'numeric', 'gt:0'],
            'unit' => ['required', 'string', 'max:20'],
            'waste_surcharge' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rounding' => ['nullable', Rule::enum(RoundingMode::class)],
            'is_tool' => ['nullable', 'boolean'],
        ]);

        // Verhältnisanteil: das gemeinsame Mengenfeld des Formulars trägt den
        // Anteil, wenn kein eigenes ratio_part übermittelt wurde.
        $isRatio = $data['quantity_kind'] === QuantityKind::Ratio->value;
        $ratioPart = $isRatio ? ($data['ratio_part'] ?? $data['quantity'] ?? null) : null;
        if ($isRatio && ($ratioPart === null || (float) $ratioPart <= 0)) {
            return back()->withErrors(['ratio_part' => __('recipes.error.ratio_required')])->withInput();
        }

        $article = Article::query()
            ->whereKey($this->decodeSqid(Article::class, (string) $data['article']))
            ->first();
        abort_unless($article instanceof Article, 404);

        $existing = null;
        if (is_string($data['requirement'] ?? null) && $data['requirement'] !== '') {
            $existing = $this->requirementOf($version, (string) $data['requirement']);
        }

        $attributes = [
            'article_id' => $article->id,
            'quantity_kind' => $data['quantity_kind'],
            // Spalte ist NOT NULL (Default 0); bei Verhältnis zählt nur ratio_part.
            'quantity' => $isRatio ? '0' : ($data['quantity'] ?? '0'),
            'ratio_part' => $ratioPart,
            'unit' => trim((string) $data['unit']),
            'waste_surcharge' => $data['waste_surcharge'] ?? null,
            'rounding' => $data['rounding'] ?? null,
            'is_tool' => (bool) ($data['is_tool'] ?? false),
            'active' => true,
        ];

        if ($existing instanceof ProcedureMaterialRequirement) {
            $existing->update($attributes);
        } else {
            $position = (int) ($version->materialRequirements()->max('position') ?? 0) + 10;
            ProcedureMaterialRequirement::query()->create($attributes + [
                'procedure_template_version_id' => $version->id,
                'position_code' => 'P' . str_pad((string) $position, 4, '0', STR_PAD_LEFT),
                'position' => $position,
            ]);
        }

        return $this->backToRecipe($template)->with('success', __('recipes.flash.material_saved'));
    }

    public function destroyMaterial(ProcedureTemplate $template, ProcedureTemplateVersion $version, ProcedureMaterialRequirement $requirement): RedirectResponse {
        Gate::authorize('update', $template);
        $this->assertDraft($template, $version);
        abort_unless($requirement->procedure_template_version_id === $version->id, 404);

        $requirement->delete();

        return $this->backToRecipe($template)->with('success', __('recipes.flash.material_removed'));
    }

    /** Partyservice-Profil (Grundausbeute + Allergen-Abweichungen) speichern. */
    public function saveProfile(Request $request, ProcedureTemplate $template, ProcedureTemplateVersion $version): RedirectResponse {
        Gate::authorize('update', $template);
        $this->assertVersionOf($template, $version);
        $organization = $template->organization;
        abort_unless($organization !== null && $this->recipes->isPartyserviceActive($organization), 404);

        $codes = $this->allergenCodes($template);
        $data = $request->validate([
            'base_portions' => ['required', 'numeric', 'gt:0'],
            'base_yield_qty' => ['nullable', 'numeric', 'gt:0'],
            'yield_unit' => ['nullable', 'string', 'max:20'],
            'allergen_added' => ['nullable', 'array'],
            'allergen_added.*' => ['string', Rule::in($codes)],
            'allergen_removed' => ['nullable', 'array'],
            'allergen_removed.*' => ['string', Rule::in($codes)],
            'override_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $added = array_values(array_unique((array) ($data['allergen_added'] ?? [])));
        $removed = array_values(array_unique((array) ($data['allergen_removed'] ?? [])));
        $reason = trim((string) ($data['override_reason'] ?? ''));

        if (($added !== [] || $removed !== []) && $reason === '') {
            return back()->withErrors(['override_reason' => __('recipes.error.override_reason_required')])->withInput();
        }

        $profile = RecipeProfile::query()->updateOrCreate(
            ['procedure_template_version_id' => $version->id],
            [
                'organization_id' => $template->organization_id,
                'base_portions' => $data['base_portions'],
                'base_yield_qty' => $data['base_yield_qty'] ?? null,
                'yield_unit' => ($data['yield_unit'] ?? null) !== null ? trim((string) $data['yield_unit']) : null,
                'allergen_overrides' => $added === [] && $removed === [] && $reason === ''
                    ? null
                    : ['added' => $added, 'removed' => $removed, 'reason' => $reason],
            ],
        );
        $profile->audit('recipe.profile_saved', [
            'version' => $version->version,
            'allergen_added' => $added,
            'allergen_removed' => $removed,
        ]);

        return $this->backToRecipe($template)->with('success', __('recipes.flash.profile_saved'));
    }

    /** Allergen-Klassifikationen einer Zutat direkt aus der Rezeptpflege pflegen. */
    public function saveIngredientAllergens(Request $request, ProcedureTemplate $template, ProcedureTemplateVersion $version, Article $article): RedirectResponse {
        Gate::authorize('update', $template);
        $this->assertVersionOf($template, $version);
        $organization = $template->organization;
        abort_unless($organization !== null && $this->recipes->isPartyserviceActive($organization), 404);
        abort_unless($version->materialRequirements()->where('article_id', $article->id)->exists(), 404);

        $codes = $this->allergenCodes($template);
        $data = $request->validate([
            'allergens' => ['required', 'array', 'min:1'],
            'allergens.*' => ['string', Rule::in($codes)],
        ]);

        $ids = Classification::query()
            ->where('domain', ClassificationDomain::Allergen->value)
            ->where(function ($q) use ($template): void {
                $q->whereNull('organization_id')->orWhere('organization_id', $template->organization_id);
            })
            ->whereIn('code', (array) $data['allergens'])
            ->pluck('id');

        // Nur die Allergen-Domäne synchronisieren; andere Klassifikationen bleiben.
        $keep = $article->classifications()
            ->where('domain', '!=', ClassificationDomain::Allergen->value)
            ->pluck('classifications.id');
        $article->classifications()->sync($keep->merge($ids)->all());
        $article->audit('recipe.ingredient_allergens', ['codes' => $data['allergens']]);

        return $this->backToRecipe($template)->with('success', __('recipes.flash.allergens_saved'));
    }

    /** @return list<string> */
    private function allergenCodes(ProcedureTemplate $template): array {
        return array_values(Classification::query()
            ->where('domain', ClassificationDomain::Allergen->value)
            ->where(function ($q) use ($template): void {
                $q->whereNull('organization_id')->orWhere('organization_id', $template->organization_id);
            })
            ->where('active', true)
            ->pluck('code')
            ->map(static fn($code): string => (string) $code)
            ->all());
    }

    private function requirementOf(ProcedureTemplateVersion $version, string $sqid): ?ProcedureMaterialRequirement {
        $id = $this->decodeSqid(ProcedureMaterialRequirement::class, $sqid);

        return $id === null ? null : ProcedureMaterialRequirement::query()
            ->where('procedure_template_version_id', $version->id)
            ->whereKey($id)
            ->first();
    }

    private function assertDraft(ProcedureTemplate $template, ProcedureTemplateVersion $version): void {
        $this->assertVersionOf($template, $version);
        abort_if($version->isPublished(), 422, (string) __('recipes.error.published_immutable'));
    }

    private function assertVersionOf(ProcedureTemplate $template, ProcedureTemplateVersion $version): void {
        abort_unless($version->procedure_template_id === $template->id, 404);
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
    private function decodeSqid(string $modelClass, string $sqid): ?int {
        return app(\App\Services\SqidEncoder::class)->decode($modelClass, $sqid);
    }

    private function backToRecipe(ProcedureTemplate $template): RedirectResponse {
        return redirect()->route('procedures.edit', $template)->withFragment('rezeptur');
    }
}
