<?php
/*
 * Created on   : Tue Nov 10 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ExtensionModuleGateRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use App\Enums\Modules\ModuleKind;
use App\Modules\ModuleRegistry;
use App\Services\Licensing\ModuleExtensions;
use App\Services\Notification\DeadlineScans\{AbstractDeadlineScan, DeadlineScan};
use CommonToolkit\Helper\FileSystem\File;
use ReflectionClass;
use Tests\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Gate MVP-1587: Der Sammler eines Erweiterungspunkts, der Meldungen, Listen
 * oder Kennzahlen eines Moduls an Nutzer bringt, filtert die Beiträge über
 * {@see ModuleExtensions} nach dem Modulstatus der Organisation — nicht jeder
 * Beitrag für sich. Ein neuer Erweiterungspunkt muss sich entscheiden:
 * {@see GATED} oder {@see UNGATED} mit Grund.
 */
class ExtensionModuleGateRuleTest extends TestCase {
    use ScansSourceTree;

    /** Erweiterungspunkte, deren Sammler nach Modul filtert. */
    private const array GATED = [
        'DeadlineScan', 'EarlyWarningSource', 'ApprovalInboxSubject', 'CockpitPanel', 'WorkItemSource',
        'ManagementKpiSource', 'CalendarEventSource', 'SearchDomain', 'PortalNoticeSource', 'FinanceMovementSource',
        'SyncCommandHandler',
    ];

    /** Dateien, die einen gefilterten Punkt roh lesen dürfen — mit Grund. */
    private const array RAW_READERS = [
        // Fristen-Scans laufen über alle Organisationen; gefiltert wird je Organisation in AbstractDeadlineScan.
        'app/Services/Notification/DeadlineScans/DeadlineScanRegistry.php' => 'DeadlineScan',
        // Die Befehlstypen gelten für die Validierung aller Organisationen; geprüft wird je Befehl vor der Ausführung (MVP-1593).
        'app/Services/Sync/SyncCommandService.php' => 'SyncCommandHandler',
    ];

    /** Erweiterungspunkte ohne Modulfilter im Sammler — Grund je Gruppe. */
    private const array UNGATED = [
        // Aufbewahrung, Löschung und Austritt laufen auch für abgeschaltete Module (wie #[RunsWhenModuleInactive]).
        'RetentionPolicyProvider', 'OffboardingStep', 'OffboardingHandover',
        // Technische Register ohne Organisationsbezug: Typen, Werkzeuge, Bedingungen, Installationsschritte.
        'FieldExtension', 'EntitySpec', 'SearchSource', 'DemoBlock', 'McpTool', 'NavigationCondition', 'PluginCapabilitySource',
        'ProfileInstallStep', 'RuleAction', 'RuleTrigger', 'MirrorPdfRenderer', 'ArticleCatalogSource',
        // Handler und Quellen, die ein Vorgang gezielt anfordert; der Vorgang selbst liegt hinter dem Modul-Gate seiner Route.
        'AllocationTargetHandler', 'TransferSource', 'InboxGroupBooker', 'CloudIntakeHandler', 'MailIntakeHandler', 'MailIntakeAnnotator',
        'MailableDocumentProvider', 'IntakeHandoverTarget', 'IntakeUploadChannel', 'VehicleReservationGuard', 'RoomBlockingSource',
        'ProjectEconomicsDimension', 'LiquidityForecastSource', 'DocumentChainSource', 'DocumentRelationSource', 'DocumentIssuer',
        'CommissionDocumentSource', 'IncomingInvoiceTransferTarget', 'IncomingPartyLookup', 'ExternalInvoiceProvider', 'CalendarImportFeed',
        'OwnershipConflictReplay',
    ];

    public function test_gated_extension_points_are_read_through_the_module_gate(): void {
        $violations = [];
        foreach ($this->phpFiles('app') as $file) {
            $relative = $this->relativePath($file);
            if ($relative === 'app/Services/Licensing/ModuleExtensions.php') {
                continue;
            }
            if (preg_match_all('/->extensions\(\s*\\\\?(?:[A-Za-z0-9_]+\\\\)*([A-Za-z0-9_]+)::class/', (string) File::read($file), $matches) === 0) {
                continue;
            }
            foreach ($matches[1] as $interface) {
                if (in_array($interface, self::GATED, true) && (self::RAW_READERS[$relative] ?? null) !== $interface) {
                    $violations[] = "{$relative}: {$interface} roh gelesen — ModuleExtensions::activeFor() nutzen";
                }
            }
        }

        $this->assertSame([], $violations, "Sammler ohne Modulfilter (MVP-1587):\n" . implode("\n", $violations));
    }

    public function test_every_extension_point_is_classified(): void {
        $points = [];
        foreach ($this->app->make(ModuleRegistry::class)->all() as $manifest) {
            foreach (array_keys($manifest->extensions()) as $interface) {
                $points[class_basename($interface)] = true;
            }
        }
        foreach ($this->phpFiles('app') as $file) {
            if (preg_match_all('/->(?:extensions|activeFor|contribute)\(\s*\\\\?(?:[A-Za-z0-9_]+\\\\)*([A-Za-z0-9_]+)::class/', (string) File::read($file), $matches) > 0) {
                foreach ($matches[1] as $interface) {
                    $points[$interface] = true;
                }
            }
        }
        $unknown = array_values(array_diff(array_keys($points), self::GATED, self::UNGATED));
        sort($unknown);

        $this->assertGreaterThanOrEqual(40, count($points));
        $this->assertSame([], $unknown, 'Erweiterungspunkt ohne Entscheidung zum Modulfilter (GATED oder UNGATED mit Grund): ' . implode(', ', $unknown));
        $this->assertSame([], array_values(array_intersect(self::GATED, self::UNGATED)));
    }

    public function test_deadline_scans_filter_per_organization(): void {
        $modules = $this->app->make(ModuleRegistry::class);
        $violations = [];
        $feature = 0;
        foreach ($modules->extensions(DeadlineScan::class) as $class) {
            if (! is_subclass_of($class, AbstractDeadlineScan::class)) {
                $violations[] = "{$class}: erbt nicht von AbstractDeadlineScan";

                continue;
            }
            $manifest = $modules->extensionOwner($class);
            if ($manifest === null || $manifest->kind() !== ModuleKind::Feature || $manifest->licenseCode() === null) {
                continue;
            }
            $feature++;
            $source = (string) File::read((string) (new ReflectionClass($class))->getFileName());
            if (preg_match('/->(runScan|sumPerOrganization|moduleActiveFor)\(/', $source) !== 1) {
                $violations[] = "{$class} ({$manifest->code()}): eigene Schleife ohne moduleActiveFor()";
            }
        }
        $base = (string) File::read((string) (new ReflectionClass(AbstractDeadlineScan::class))->getFileName());

        $this->assertGreaterThanOrEqual(15, $feature);
        $this->assertSame(3, substr_count($base, '$this->moduleActiveFor(') + substr_count($base, 'function moduleActiveFor('), 'runScan() und sumPerOrganization() müssen nach Modul filtern.');
        $this->assertSame([], $violations, "Fristen-Scans ohne Modulfilter (MVP-1587):\n" . implode("\n", $violations));
    }
}
