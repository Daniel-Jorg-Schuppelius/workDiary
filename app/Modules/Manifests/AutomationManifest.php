<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AutomationManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „Automationsregeln“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class AutomationManifest extends Manifest {
    public function code(): string {
        return 'automation';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Core;
    }

    public function label(): string {
        return 'Automationsregeln';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Automation',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'automation_rule_runs',
            'automation_rules',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Automation\Triggers\RuleTrigger::class => [
                \App\Automation\Triggers\UserCreatedTrigger::class,
            ],
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function listeners(): array {
        return [
            // Regeln auf „Mitarbeitende angelegt“ (MVP-1273).
            \App\Events\Platform\UserCreated::class => [
                \App\Listeners\Automation\DispatchUserCreatedRules::class,
            ],
        ];
    }
}
