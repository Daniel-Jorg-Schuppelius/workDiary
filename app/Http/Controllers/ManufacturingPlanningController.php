<?php
/*
 * Created on   : Fri Jun 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ManufacturingPlanningController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Enums\Procedure\ProcedureStepType;
use App\Models\{Article, ManufacturingOrder, ProcedureTemplateVersion};
use App\Services\Manufacturing\{ManufacturingQualityService, MrpService};
use App\Services\Procedure\SpcService;
use App\Services\SqidEncoder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Fertigungsplanung (Feature 047/048, E7): mehrstufige Materialbedarfsauflösung
 * (MRP) für ein Erzeugnis und Qualitätskennzahlen (SPC) je Artikel. Lesen mit
 * der Fertigungs-Berechtigung; Modul-Gating über `manufacturing-planning.*`.
 */
class ManufacturingPlanningController extends Controller {
    public function __construct(
        private readonly MrpService $mrp,
        private readonly ManufacturingQualityService $quality,
        private readonly SpcService $spc,
    ) {}

    public function index(Request $request): View {
        Gate::authorize('viewAny', ManufacturingOrder::class);

        $articles = Article::query()->where('manufacturable', true)->orderBy('name')->limit(500)->get();
        $articleId = app(SqidEncoder::class)->decode(Article::class, $request->string('article')->toString());
        $article = $articleId !== null ? Article::query()->find($articleId) : $articles->first();
        $qty = $request->string('qty')->toString() ?: '1';

        $lines = $article instanceof Article ? $this->mrp->explode($article, null, $qty) : [];
        $articleNames = Article::query()
            ->whereIn('id', array_values(array_unique(array_column($lines, 'article_id'))))
            ->pluck('name', 'id');

        return view('manufacturing.planning', [
            'articles' => $articles,
            'article' => $article,
            'qty' => $qty,
            'lines' => $lines,
            'articleNames' => $articleNames,
            'metrics' => $article instanceof Article ? $this->quality->metricsForArticle((int) $article->id) : null,
            'spc' => $article instanceof Article ? $this->spcFor($article) : [],
        ]);
    }

    /**
     * SPC-Kennzahlen der Mess-Schritte des Arbeitsplans des Artikels.
     *
     * @return list<array{label: string, metrics: array<string, mixed>}>
     */
    private function spcFor(Article $article): array {
        $version = $article->defaultProcedureVersion;
        if (! $version instanceof ProcedureTemplateVersion) {
            return [];
        }

        $out = [];
        foreach ($version->steps()->where('step_type', ProcedureStepType::Messreihe->value)->get() as $step) {
            $metrics = $this->spc->analyzeStep($step);
            if ($metrics !== null) {
                $out[] = ['label' => (string) $step->label, 'metrics' => $metrics];
            }
        }

        return $out;
    }
}
