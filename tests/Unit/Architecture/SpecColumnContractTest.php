<?php
/*
 * Created on   : Sat Jul 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SpecColumnContractTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Kontrakt-Gate für Import-/Export-Spec-Paare (Vollaudit 2026-07, N55):
 * Jede exportierte Spalte muss vom Import-Pendant verstanden werden
 * (Export ⊆ Import), sonst bricht der Roundtrip Export → Import still.
 * Bewusste Abgrenzungen (z. B. Customer `external_id`, das der Import als
 * Fremd-ID über ExternalReferences auflöst) gehören in den Import — nicht
 * in den Export — und sind dort kommentiert.
 */
class SpecColumnContractTest extends TestCase {
    /**
     * Export-Spec → Import-Spec (nur Entitäten, die beides besitzen; Tour
     * hat keinen Import, Vehicle/Supplier/Article keinen Export).
     *
     * @var array<class-string, class-string>
     */
    private const PAIRS = [
        \App\Services\Export\Specs\CustomerExportSpec::class => \App\Services\Import\Specs\CustomerSpec::class,
        \App\Services\Export\Specs\MaterialExportSpec::class => \App\Services\Import\Specs\MaterialSpec::class,
        \App\Services\Export\Specs\ProjectExportSpec::class => \App\Services\Import\Specs\ProjectSpec::class,
        \App\Services\Export\Specs\ScheduledShiftExportSpec::class => \App\Services\Import\Specs\ScheduledShiftSpec::class,
        \App\Services\Export\Specs\UserExportSpec::class => \App\Services\Import\Specs\UserSpec::class,
    ];

    public function test_export_columns_are_subset_of_import_columns(): void {
        $violations = [];

        foreach (self::PAIRS as $exportClass => $importClass) {
            $export = (new ReflectionClass($exportClass))->newInstanceWithoutConstructor();
            $import = (new ReflectionClass($importClass))->newInstanceWithoutConstructor();

            $extra = array_diff($export->columns(), $import->columns());
            if ($extra !== []) {
                $violations[] = sprintf(
                    '%s exportiert Spalten, die %s nicht importiert: %s',
                    class_basename($exportClass),
                    class_basename($importClass),
                    implode(', ', $extra),
                );
            }
        }

        $this->assertSame([], $violations, implode("\n", array_merge($violations, [
            'Spalte im Import-Spec ergänzen (inkl. headerAliases) oder den Export-Überhang fachlich begründet entfernen.',
        ])));
    }
}
