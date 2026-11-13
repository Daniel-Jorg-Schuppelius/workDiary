<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DelegationManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/**
 * Vertretung bei Abwesenheit (Feature 202, MVP-1647): Baustein der Plattform.
 * Erweiterungspunkt `DelegableWork` (Bereiche zugewiesener Arbeit, gemeldet von
 * den Fachmodulen), Dienst `ActsForResolver` für Arbeitsvorrat,
 * Benachrichtigungen und — ab MVP-1648 — Policies und „Meine“-Sichten. Das
 * Recht `user.delegation.manage` liegt in der Gruppe „Mitglieder“ der Plattform.
 */
final class DelegationManifest extends Manifest {
    public function code(): string {
        return 'delegation';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Vertretung bei Abwesenheit';
    }

    public function description(): string {
        return 'Vorübergehende Vertretung für die zugewiesene Arbeit einer abwesenden Person, ohne zusätzliche Rechte.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Delegation',
        ];
    }

    /** @return list<string> Nur zur Doku: Plattformrouten sind nicht lizenzgebunden. */
    public function routePatterns(): array {
        return [
            'delegations.*',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'work_delegations',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Delegation\Demo\DelegationDemoBlock::class,
            ],
        ];
    }
}
