<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RetentionSection.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Finance\ProcedureDocumentation\Sections;

use App\Models\Organization;
use App\Services\Finance\ProcedureDocumentation\{FormatsSectionValues, ProcedureSection, SectionContext};
use App\Services\Privacy\Retention\RetentionRegistry;

/**
 * Aufbewahrungsbereiche (config/retention.php inkl. der Bereiche aus MVP-694)
 * aufgelöst über den Rechtsraum der Organisation: Frist + Rechtsgrundlage.
 */
final class RetentionSection implements ProcedureSection {
    use FormatsSectionValues;

    public function __construct(private readonly RetentionRegistry $registry) {}

    public function key(): string {
        return 'retention';
    }

    public function title(): string {
        return (string) __('procedure-documentation.section.retention');
    }

    public function build(Organization $organization, SectionContext $context): array {
        $region = $this->registry->regionFor($organization);
        $rows = [];
        /** @var array<string, array<string, mixed>> $areas */
        $areas = (array) config('retention.areas', []);
        foreach ($areas as $area => $config) {
            $period = isset($config['days_source'])
                ? $this->days($this->registry->daysFor($area))
                : $this->years($this->registry->yearsFor($organization, $area));
            $rows[] = [
                (string) ($config['label'] ?? $area),
                $area,
                $period,
                $this->text($this->registry->basisFor($organization, $area)),
            ];
        }

        return [
            'fields' => [
                'region' => $this->field('procedure-documentation.retention.region', $region),
            ],
            'tables' => [
                'areas' => [
                    'title' => (string) __('procedure-documentation.retention.table'),
                    'columns' => [
                        (string) __('procedure-documentation.retention.col.area'),
                        (string) __('procedure-documentation.retention.col.key'),
                        (string) __('procedure-documentation.retention.col.period'),
                        (string) __('procedure-documentation.retention.col.basis'),
                    ],
                    'rows' => $rows,
                ],
            ],
        ];
    }

    private function years(?int $years): string {
        return $years === null ? '—' : (string) __('procedure-documentation.retention.years', ['count' => $years]);
    }

    private function days(?int $days): string {
        if ($days === null || $days === 0) {
            return (string) __('procedure-documentation.retention.unlimited');
        }

        return (string) __('procedure-documentation.retention.days', ['count' => $days]);
    }
}
