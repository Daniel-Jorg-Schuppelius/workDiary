<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BelongsToModule.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Attributes;

use Attribute;

/**
 * Modul eines Jobs oder Konsolenbefehls, der nicht in einem Domänenordner
 * liegt (MVP-1518, MVP-1524). Ein Umzug in den Ordner bräche bereits
 * eingereihte Jobs: die Nutzlast trägt den Klassennamen.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class BelongsToModule {
    public function __construct(public string $module) {}
}
