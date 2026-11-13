<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : WishesManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/**
 * Wünsche an die App (Feature 206, MVP-1658/1659/1677): Baustein der
 * Plattform. Wünsche je Organisation mit Stimmen und Antwort, Weitergabe an
 * den Plattformbetrieb ohne Personenbezug, Herstellerkanal per Mail mit
 * eigenem Opt-in (`wishes.vendor_channel`, Vorgabe aus). Das Recht
 * `wishes.manage` liegt in der Gruppe „Organisation“ der Plattform.
 */
final class WishesManifest extends Manifest {
    public function code(): string {
        return 'wishes';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Platform;
    }

    public function label(): string {
        return 'Wünsche an die App';
    }

    public function description(): string {
        return 'Wünsche, Fehler und Fragen der Mitarbeitenden mit Stimmen und Antwort; Weitergabe an den Plattformbetrieb ohne Personenbezug.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Wishes',
        ];
    }

    /** @return list<string> Nur zur Doku: Plattformrouten sind nicht lizenzgebunden. */
    public function routePatterns(): array {
        return [
            'wishes.*',
            'admin.wishes.*',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'app_wishes',
            'app_wish_votes',
        ];
    }

    /** @return array<class-string, list<class-string>> */
    public function extensions(): array {
        return [
            \App\Services\Demo\Contracts\DemoBlock::class => [
                \App\Services\Wishes\Demo\WishesDemoBlock::class,
            ],
        ];
    }
}
