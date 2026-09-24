<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ModuleUnavailableException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules;

use App\Services\Licensing\ModuleCatalog;
use RuntimeException;

/** Eine Null-Bindung wurde für eine Wirkung gerufen, die das fehlende Modul bräuchte (MVP-863). */
final class ModuleUnavailableException extends RuntimeException {
    public static function for(string $moduleCode): self {
        $label = app(ModuleCatalog::class)->label($moduleCode);

        return new self((string) __('Das Modul „:modul" ist nicht aktiv.', ['modul' => $label]));
    }
}
