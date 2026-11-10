<?php
/*
 * Created on   : Mon Nov 09 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RunsWhenModuleInactive.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Attributes;

use Attribute;

/**
 * Geplanter Befehl eines Fachmoduls, der auch bei abgeschaltetem Modul läuft
 * (MVP-1524): Aufbewahrungs-, Lösch- und Sicherheitspflichten gelten
 * unabhängig davon, ob das Modul gerade genutzt wird. Der Grund ist Pflicht
 * (Gate `ModuleCommandRuleTest`).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class RunsWhenModuleInactive {
    public function __construct(public string $reason) {}
}
