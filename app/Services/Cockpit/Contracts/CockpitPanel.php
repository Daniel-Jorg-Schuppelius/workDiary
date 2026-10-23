<?php
/*
 * Created on   : Fri Oct 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CockpitPanel.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Cockpit\Contracts;

use App\Models\Platform\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;

/**
 * Panel eines Moduls im Cockpit eines Geschäftsobjekts (Feature 182). Das
 * Panel prüft Modul, Recht und Mandant selbst, zeigt nur vorhandene Daten und
 * liefert `null`, wenn es nichts zu zeigen gibt.
 */
interface CockpitPanel {
    /** Position im Cockpit; kleinere Werte zuerst. */
    public function order(): int;

    public function supports(Model $subject): bool;

    public function render(Model $subject, User $viewer): ?View;
}
