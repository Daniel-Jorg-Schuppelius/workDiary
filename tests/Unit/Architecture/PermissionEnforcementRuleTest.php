<?php

/*
 * Filename     : PermissionEnforcementRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „jeder Rechteschlüssel hat eine Prüfstelle" (Vollscan
 * 2026-09-15, Befund `C1-01` / `MVP-795`).
 *
 * Befund (nachgemessen am 2026-09-16): 101 der 486 Fälle des `Permission`-Enums
 * kommen ausserhalb des Enums und des Seeders nirgends vor — 79 davon vergibt
 * der Seeder trotzdem an Rollen, und der Rollen-Editor zeigt sie. Ein
 * Administrator schaltet dort also Rechte, die nichts bewirken. Die übrigen 22
 * stehen nur im Enum. Schwerpunkte: Lieferant und Stundenzettel je 7,
 * Zeiteintrag 5, Kundenportal, Fremdkunde, Rechnung und Prozedur je 4.
 *
 * Als Prüfstelle zählt jede Nennung ausserhalb von Enum und Seeder: die drei
 * gebräuchlichen Aliasse (`P::`, `Permission::`, `PermissionEnum::`) oder der
 * Rohwert. Der Rohwert zählt ausdrücklich auch in Zuordnungstabellen wie
 * `ImportEntity::permission()`: die Prüfung erfolgt dort indirekt über
 * `hasEffectivePermission($entity->permission())` im Import-/Export-Controller
 * und ist damit echt. Das ist bewusst weit gefasst — es geht um „wird überhaupt
 * irgendwo ausgewertet", nicht um die Form des Aufrufs. Enger zu messen hiesse,
 * die vier verbreiteten Formen (`Gate::allows`, `->can`,
 * `hasEffectivePermission`, `@can`) jeweils exakt treffen zu müssen; genau daran
 * sind zwei Vormessungen gescheitert.
 *
 * Die BASELINE friert den Stand vom 2026-09-16 ein und darf nur **schrumpfen**.
 * Die Umstellung der Altfälle läuft domänenweise: sie ist keine Mechanik,
 * sondern je Domäne eine Entscheidung darüber, wer künftig darf — heute geben
 * die betroffenen Policies überwiegend `true` zurück oder prüfen Eigentümer-
 * schaft, sodass eine blosse Umstellung Rollen den Zugriff entzöge.
 */
class PermissionEnforcementRuleTest extends TestCase {
    use ScansSourceTree;

    /** Dateien, die Rechte nur DEFINIEREN oder VERGEBEN, nicht auswerten. */
    private const DEFINITION_ONLY = [
        'app/Enums/User/Permission.php',
        'database/seeders/PermissionsSeeder.php',
    ];

    /**
     * Altfälle ohne Prüfstelle (Stand 2026-09-16). Nur kürzer werden.
     *
     * @var list<string>
     */
    private const BASELINE = [
        'AccessAssignGroups', 'AccessAssignRoles', 'AccessAuditView', 'ActivityCategoryManage',
        'AssetBlockOverride', 'AttendanceManage',
        'AttendanceViewAny', 'AuditLogView',
        'BrandingUpdate', 'CoverageRequirementManage', 'CustomerDelete',
        'CustomerLexofficeSync', 'CustomerPortalDiaryView',
        'CustomerPortalInvoiceView', 'CustomerPortalOpenIssueView', 'CustomerPortalTimeEntryView',
        'DiaryCreateForOthers', 'DiaryExport', 'DiaryViewOwn', 'DomainInvoiceDownload',
        'DomainInvoiceView', 'DutyPlanCreate', 'DutyPlanDelete', 'DutyPlanPublish', 'DutyPlanUpdate',
        'EmergencyAssignmentManage', 'EnergyLogManage', 'EntryTypeManage', 'FlexBalanceManage',
        'FlexBalanceView', 'ForeignCustomerCreate', 'ForeignCustomerDelete', 'ForeignCustomerPromote',
        'ForeignCustomerUpdate', 'HolidayManage', 'ImportViewReports', 'InvoiceDelete',
        'InvoiceIssue', 'InvoicePay', 'MaterialManage',
        'MilestoneManage', 'OnCallShiftManage', 'OpenIssueCreate', 'OpenIssueUpdate',
        'OrganizationBilling', 'OrganizationView', 'PlatformDemoCreate', 'ProcedureSecondPersonRequest',
        'ProcedureSecondPersonRevoke', 'ProcedureSecondPersonSign', 'ProcedureSecondPersonTake',
        'ProjectArchive', 'ProjectCreate', 'ProjectDelete', 'ProjectManageBilling', 'ProtocolCreate', 'ProtocolItemPhotoAdd', 'ProtocolItemPhotoRemove', 'QualificationManage',
        'ScheduledShiftManage', 'ShiftManage', 'ShiftTypeManage',
        'SickLeaveManage', 'SupplierCreate', 'SupplierDelete', 'SupplierExport', 'SupplierLexofficeSync', 'SupplierUpdate', 'SupplierView', 'SupplierViewAny', 'TagManage',
        'TaskManage', 'TimeEntryCreate', 'TimeEntryCreateForOthers',
        'TimeEntryDelete', 'TimeEntryUpdate', 'TimeEntryViewOwn', 'TimesheetCreate', 'TimesheetDelete',
        'TimesheetExport', 'TimesheetLock', 'TimesheetSign', 'TimesheetUnlock', 'TimesheetUpdate',
        'TimesheetViewAny', 'TourManage', 'TourViewAny', 'TravelLogManage', 'TravelLogViewAny',
        'UserCreate', 'UserDelete', 'UserManageRates', 'UserResetPassword', 'UserUpdate',
        'UserView', 'UserViewAny', 'VacationApprove', 'VacationCancel', 'VacationRequest',
    ];

    public function test_every_permission_has_an_enforcement_point(): void {
        $unenforced = $this->unenforcedPermissions();
        $new = array_diff(array_keys($unenforced), self::BASELINE);

        $this->assertSame([], array_values($new), "Rechteschlüssel ohne jede Prüfstelle:\n"
            . implode("\n", array_map(
                static fn (string $case): string => sprintf('  %s (%s)', $case, $unenforced[$case]),
                $new,
            ))
            . "\n\nEin vergebenes Recht, das nirgends ausgewertet wird, ist eine Attrappe: der Rollen-Editor\n"
            . "zeigt es, das Schalten bewirkt nichts. Entweder eine Prüfstelle ergänzen (Policy, Gate,\n"
            . 'can-Middleware) oder den Enum-Fall samt Seeder-Vergabe entfernen.');
    }

    public function test_baseline_has_no_stale_entries(): void {
        $unenforced = $this->unenforcedPermissions();
        $stale = array_diff(self::BASELINE, array_keys($unenforced));

        $this->assertSame([], array_values($stale), 'Diese BASELINE-Einträge werden inzwischen geprüft — '
            . "Eintrag entfernen (die Ausnahmeliste darf nur schrumpfen):\n" . implode("\n", $stale));
    }

    public function test_the_permission_inventory_is_actually_scanned(): void {
        // Schutz gegen ein stilles Leerlaufen (falscher Pfad, kaputte Regex).
        $this->assertGreaterThan(400, count($this->permissionCases()));
    }

    /**
     * Enum-Fälle ohne jede Nennung ausserhalb von Enum und Seeder.
     *
     * @return array<string, string> Fallname → Rechteschlüssel
     */
    private function unenforcedPermissions(): array {
        $haystack = $this->enforcementHaystack();
        $unenforced = [];

        foreach ($this->permissionCases() as $case => $value) {
            foreach (['P', 'Permission', 'PermissionEnum'] as $alias) {
                if (str_contains($haystack, $alias . '::' . $case)) {
                    continue 2;
                }
            }
            if (str_contains($haystack, "'" . $value . "'") || str_contains($haystack, '"' . $value . '"')) {
                continue;
            }
            $unenforced[$case] = $value;
        }

        ksort($unenforced);

        return $unenforced;
    }

    /** @return array<string, string> */
    private function permissionCases(): array {
        $source = (string) file_get_contents($this->repoRoot() . '/app/Enums/User/Permission.php');
        preg_match_all("/case\s+(\w+)\s*=\s*'([^']+)'/", $source, $matches, PREG_SET_ORDER);

        $cases = [];
        foreach ($matches as $match) {
            $cases[$match[1]] = $match[2];
        }

        return $cases;
    }

    private function enforcementHaystack(): string {
        $haystack = '';
        foreach (['app', 'routes', 'resources'] as $directory) {
            foreach ($this->phpFiles($directory) as $file) {
                if ($this->isDefinitionOnly($file)) {
                    continue;
                }
                $haystack .= (string) file_get_contents($file);
            }
        }
        foreach ($this->bladeFiles() as $file) {
            $haystack .= (string) file_get_contents($file);
        }

        return $haystack;
    }

    private function isDefinitionOnly(string $absolute): bool {
        $relative = $this->relativePath($absolute);
        foreach (self::DEFINITION_ONLY as $needle) {
            if ($relative === $needle) {
                return true;
            }
        }

        return false;
    }
}
