<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CockpitPanels.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cockpit;

use App\Models\Platform\User;
use App\Modules\ModuleRegistry;
use App\Services\Cockpit\Contracts\CockpitPanel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Throwable;

/** Sammelt die Panels der Module für ein Cockpit (Feature 182), sortiert nach `order()`. */
final class CockpitPanels {
    public function __construct(private readonly ModuleRegistry $modules) {}

    /** @return list<HtmlString> gerenderte Panels — Blade gibt sie mit `{{ }}` unverändert aus */
    public function render(Model $subject, User $viewer): array {
        $panels = [];
        foreach ($this->modules->extensions(CockpitPanel::class) as $class) {
            /** @var CockpitPanel $panel */
            $panel = app($class);
            if ($panel->supports($subject)) {
                $panels[] = $panel;
            }
        }
        usort($panels, static fn (CockpitPanel $a, CockpitPanel $b): int => $a->order() <=> $b->order());

        $html = [];
        foreach ($panels as $panel) {
            try {
                $view = $panel->render($subject, $viewer);
                if ($view !== null) {
                    $html[] = new HtmlString($view->render());
                }
            } catch (Throwable $e) {
                // Ein Panel darf das Cockpit nicht kippen; sein Fehler wird gemeldet.
                report($e);
            }
        }

        return $html;
    }
}
