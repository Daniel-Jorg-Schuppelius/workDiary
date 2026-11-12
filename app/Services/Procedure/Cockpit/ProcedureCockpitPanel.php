<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ProcedureCockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Procedure\Cockpit;

use App\Enums\User\Permission;
use App\Models\Asset\Asset;
use App\Models\Platform\User;
use App\Models\Procedure\{ProcedureRun, ProcedureTemplate};
use App\Services\Cockpit\Contracts\CockpitPanel;
use App\Services\Procedure\ProcedureApplicabilityResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/** Prozeduren an der Asset-Akte (MVP-1571): Läufe des Assets und passende Vorlagen zum Starten. */
final class ProcedureCockpitPanel implements CockpitPanel {
    private const int LIMIT = 10;

    public function __construct(private readonly ProcedureApplicabilityResolver $resolver) {}

    public function order(): int {
        return 35;
    }

    public function supports(Model $subject): bool {
        return $subject instanceof Asset;
    }

    public function render(Model $subject, User $viewer): ?View {
        if (! $subject instanceof Asset || ! $viewer->can(Permission::ProcedureRunView->value)) {
            return null;
        }

        $runs = ProcedureRun::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->with('templateVersion.template')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $suggested = collect();
        if ($viewer->can(Permission::ProcedureRunStart->value)) {
            // Eine Vorlage mit laufendem Lauf am Asset nicht doppelt anbieten; nach dem Abschluss wieder.
            $running = $runs
                ->filter(fn (ProcedureRun $run): bool => $run->status->isActive())
                ->map(fn (ProcedureRun $run): ?int => $run->templateVersion?->procedure_template_id)
                ->filter()
                ->all();
            $suggested = $this->resolver->suggestForAsset($subject)
                ->reject(fn (ProcedureTemplate $template): bool => in_array($template->id, $running, true))
                ->values();
        }

        if ($runs->isEmpty() && $suggested->isEmpty()) {
            return null;
        }

        return view('procedures._asset_panel', ['asset' => $subject, 'runs' => $runs, 'suggested' => $suggested]);
    }
}
