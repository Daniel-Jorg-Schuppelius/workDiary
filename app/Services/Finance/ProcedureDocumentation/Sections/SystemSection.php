<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SystemSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation\Sections;

use App\Console\Commands\SystemHealthCommand;
use App\Models\Organization;
use App\Services\Finance\ProcedureDocumentation\{FormatsSectionValues, ProcedureSection, SectionContext};
use App\Services\Licensing\{LicenseService, ModuleStatusResolver};
use App\Services\Release\ReleaseManifestService;

/**
 * Systembeschreibung: Version/Build/Laufzeit aus dem Release-Manifest
 * (`release:manifest`), Modulstatus je Organisation (MVP-052), installierte
 * Plugins und das Ergebnis der `system:health`-Checks.
 */
final class SystemSection implements ProcedureSection {
    use FormatsSectionValues;

    public function __construct(
        private readonly ReleaseManifestService $manifest,
        private readonly ModuleStatusResolver $modules,
        private readonly SystemHealthCommand $health,
        private readonly LicenseService $licenses,
    ) {}

    public function key(): string {
        return 'system';
    }

    public function title(): string {
        return (string) __('procedure-documentation.section.system');
    }

    public function build(Organization $organization, SectionContext $context): array {
        $payload = $this->manifest->payload();
        /** @var array<string, mixed> $app */
        $app = is_array($payload['application'] ?? null) ? $payload['application'] : [];
        /** @var array<string, mixed> $runtime */
        $runtime = is_array($payload['runtime'] ?? null) ? $payload['runtime'] : [];
        /** @var array<string, mixed>|null $integrity */
        $integrity = is_array($payload['integrity'] ?? null) ? $payload['integrity'] : null;

        $fields = [
            'app_name' => $this->field('procedure-documentation.system.app_name', $app['name'] ?? null),
            'app_version' => $this->field('procedure-documentation.system.app_version', $app['version'] ?? null),
            'build' => $this->field('procedure-documentation.system.build', $app['build'] ?? null),
            'environment' => $this->field('procedure-documentation.system.environment', $app['environment'] ?? null),
            'php' => $this->field('procedure-documentation.system.php', $runtime['php'] ?? null),
            'laravel' => $this->field('procedure-documentation.system.laravel', $runtime['laravel'] ?? null),
            'database' => $this->field('procedure-documentation.system.database', trim($this->text($runtime['database_driver'] ?? null) . ' ' . $this->text($runtime['database_version'] ?? null))),
            'integrity_root' => $this->field('procedure-documentation.system.integrity_root', $integrity['root'] ?? null),
        ];

        $modules = [];
        foreach ($this->modules->forOrganization($organization) as $row) {
            $modules[] = [$row['code'], $row['label'], $row['status']->label(), $this->text($row['source'])];
        }

        $plugins = [];
        /** @var list<array<string, mixed>> $entries */
        $entries = is_array($payload['plugins'] ?? null) ? $payload['plugins'] : [];
        foreach ($entries as $entry) {
            $plugins[] = [
                $this->text($entry['id'] ?? null),
                $this->text($entry['name'] ?? null),
                $this->text($entry['version'] ?? null),
                $this->text($entry['min_app_version'] ?? null) . ' – ' . $this->text($entry['max_app_version'] ?? null),
            ];
        }

        $health = [];
        foreach ($this->health->runChecks($this->licenses) as $check) {
            $health[] = [
                $check[0],
                (string) __($check[1] ? 'procedure-documentation.system.ok' : 'procedure-documentation.system.failed'),
                $check[2],
            ];
        }

        return [
            'fields' => $fields,
            'tables' => [
                'modules' => [
                    'title' => (string) __('procedure-documentation.system.table.modules'),
                    'columns' => [(string) __('procedure-documentation.system.col.code'), (string) __('procedure-documentation.system.col.module'), (string) __('procedure-documentation.system.col.status'), (string) __('procedure-documentation.system.col.source')],
                    'rows' => $modules,
                ],
                'plugins' => [
                    'title' => (string) __('procedure-documentation.system.table.plugins'),
                    'columns' => [(string) __('procedure-documentation.system.col.id'), (string) __('procedure-documentation.system.col.plugin'), (string) __('procedure-documentation.system.col.version'), (string) __('procedure-documentation.system.col.compat')],
                    'rows' => $plugins,
                ],
                'health' => [
                    'title' => (string) __('procedure-documentation.system.table.health'),
                    'columns' => [(string) __('procedure-documentation.system.col.check'), (string) __('procedure-documentation.system.col.result'), (string) __('procedure-documentation.system.col.details')],
                    'rows' => $health,
                ],
            ],
        ];
    }
}
