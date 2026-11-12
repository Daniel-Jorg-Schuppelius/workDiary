<?php
/*
 * Created on   : Wed Nov 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WritesDataDomain.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Attributes;

use App\Enums\Integration\DataDomain;
use Attribute;

/**
 * Markiert einen Schreibweg eines Plugins in einen Bereich der
 * Datenführerschaft (MVP-1592). Das Gate `DataOwnershipWriteRuleTest`
 * verlangt in der Klasse die Prüfung über den Guard oder `mayWrite()`.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class WritesDataDomain {
    public function __construct(public readonly DataDomain $domain) {}
}
