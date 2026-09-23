<?php
/*
 * Created on   : Wed Sep 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : AiManifest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Modules\Manifests;

use App\Enums\Modules\ModuleKind;
use App\Modules\Manifest;

/** Modul „KI-Assistenz“ (MVP-861). Zuordnung von Tabellen, Ordnern und Routen — bei Änderungen `php artisan modules:check`. */
final class AiManifest extends Manifest {
    public function code(): string {
        return 'ai';
    }

    public function kind(): ModuleKind {
        return ModuleKind::Feature;
    }

    public function label(): string {
        return 'KI-Assistenz';
    }

    public function licenseCode(): string {
        return 'module.ai';
    }

    public function description(): string {
        return 'Optionale KI-Assistenz: Vorschläge aus konfigurierten Provider-Verbindungen (Cloud oder lokal), opt-in je Capability.';
    }

    /** @return list<string> */
    public function folders(): array {
        return [
            'Ai',
        ];
    }

    /** @return list<string> */
    public function tables(): array {
        return [
            'ai_capability_settings',
            'ai_memory_entries',
            'ai_provider_connections',
            'ai_text_suggestions',
            'ai_usage_periods',
        ];
    }

    /** @return list<string> */
    public function routePatterns(): array {
        return [
            'admin.ai.*',
            'ai.suggestions.*',
            'ai.suggest.*',
            'ai.assist.*',
        ];
    }

    /** @return array{sections: list<string>, items: list<string>, groups: list<string>} */
    public function navigation(): array {
        return [
            'sections' => [],
            'items' => [
                'admin.ai.index',
            ],
            'groups' => [],
        ];
    }
}
