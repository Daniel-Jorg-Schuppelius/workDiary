<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleListener.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Listeners;

use App\Models\Platform\Organization;
use App\Modules\ModuleRegistry;
use App\Services\Licensing\ModuleStatusResolver;

/**
 * Basis der Listener eines Fachmoduls (MVP-863): reagiert nur, wenn das Modul
 * für die Organisation aktiv ist. Kernmodule (ohne Lizenzcode) sind immer aktiv.
 */
abstract class ModuleListener {
    /** Modulcode des Manifests, zu dem der Listener gehört. */
    abstract protected function module(): string;

    protected function shouldHandle(Organization|int|null $organization): bool {
        $license = app(ModuleRegistry::class)->byCode($this->module())?->licenseCode();
        if ($license === null) {
            return true;
        }
        $org = $organization instanceof Organization ? $organization : ($organization !== null ? Organization::query()->find($organization) : null);

        return $org instanceof Organization && app(ModuleStatusResolver::class)->isActiveFor($org, $license);
    }
}
