<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PickListController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCurrentOrganization;
use App\Models\{ManufacturingOrder, Warehouse};
use App\Services\Inventory\{PickListBuilder, PickListPdfRenderer};
use App\Support\Sqid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Kommissionierliste je fachlicher Quelle (Feature 048, MVP-706): HTML-Liste
 * und PDF aus den aktiven Reservierungen. Lesen mit inventory.viewAny plus
 * Sichtrecht auf die Quelle; die Quelle kommt org-gescopt über den Sqid.
 */
class PickListController extends Controller {
    use ResolvesCurrentOrganization;

    /** @var array<string, class-string<Model>> Routen-Slug → Quellmodell */
    public const SOURCES = [
        'manufacturing-order' => ManufacturingOrder::class,
    ];

    public function show(string $source, string $sqid, PickListBuilder $builder): View {
        $model = $this->resolveSource($source, $sqid);

        return view('inventory.pick-lists.show', [
            'list' => $builder->forSource($model),
            'sourceSlug' => $source,
            'sourceSqid' => $sqid,
            'source' => $model,
        ]);
    }

    public function pdf(string $source, string $sqid, PickListBuilder $builder, PickListPdfRenderer $renderer): Response {
        $model = $this->resolveSource($source, $sqid);
        $list = $builder->forSource($model);

        return response($renderer->render($list, $this->currentOrganizationOrNull()), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $list->number() . '.pdf"',
        ]);
    }

    private function resolveSource(string $source, string $sqid): Model {
        Gate::authorize('viewAny', Warehouse::class);

        $class = self::SOURCES[$source] ?? null;
        abort_if($class === null, 404);

        $model = $class::query()->findOrFail(Sqid::decodeOrAbort($class, $sqid));
        Gate::authorize('view', $model);

        return $model;
    }
}
