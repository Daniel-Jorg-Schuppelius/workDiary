<?php
/*
 * Created on   : Sat May 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PublishedProcedureVersionLockedException.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Exceptions;

use App\Models\ProcedureTemplateVersion;
use RuntimeException;

class PublishedProcedureVersionLockedException extends RuntimeException {
    public static function forVersion(ProcedureTemplateVersion $version): self {
        return new self(sprintf(
            'Procedure template version #%d (template %d, version %d) is published and immutable.',
            $version->id,
            $version->procedure_template_id,
            $version->version,
        ));
    }
}
