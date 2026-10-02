<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NullDictationStructurer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Media\Contracts;

use App\Models\Platform\Organization;

final class NullDictationStructurer implements DictationStructurer {
    public function structure(Organization $organization, string $text, array $fields, string $locale): ?array {
        return null;
    }
}
