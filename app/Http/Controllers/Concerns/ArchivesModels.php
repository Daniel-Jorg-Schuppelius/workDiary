<?php
/*
 * Created on   : Sun Jun 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ArchivesModels.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Einheitliches Archivieren/Wiederherstellen (Soft-Archive über archived_at)
 * für Controller. Autorisierung über die Policy-Abilities `archive`/`restore`.
 * Das endgültige Löschen (destroy) bleibt bewusst pro Controller individuell,
 * da die Lösch-Vorbedingungen je Entität unterschiedlich sind.
 */
trait ArchivesModels {
    protected function archiveModel(Model $model, string $message): RedirectResponse {
        Gate::authorize('archive', $model);

        $model->forceFill(['archived_at' => now()])->save();

        return back()->with('success', $message);
    }

    protected function restoreModel(Model $model, string $message): RedirectResponse {
        Gate::authorize('restore', $model);

        $model->forceFill(['archived_at' => null])->save();

        return back()->with('success', $message);
    }
}
