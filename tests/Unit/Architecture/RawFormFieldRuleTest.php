<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawFormFieldRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „rohe Formularfelder" (Vollscan 2026-08-23, I5 / WCAG
 * 1.3.1+3.3.2): `<label class="fieldset-label">` ohne `for` und ohne
 * umschlossenes Control ist für Screenreader nicht mit dem Feld verknüpft.
 * Der Standardweg sind die Feld-Komponenten (x-input-field, x-select-field,
 * x-textarea-field, x-checkbox-field, x-date-range) — sie rendern label/for,
 * aria-describedby und Fehleranzeige automatisch.
 *
 * Regel: Kein NEUES fieldset-label ohne for-Verknüpfung. Der Bestand steht
 * als BASELINE (Datei → Anzahl) und wird beim Umstellen auf die Komponenten
 * abgetragen — sinkt die Zahl, MUSS der Eintrag hier reduziert/gestrichen
 * werden; steigen darf sie nie. Labels, die ihr Control umschließen
 * (Checkbox-/Toggle-Muster), sind implizit verknüpft und zählen nicht.
 */
class RawFormFieldRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, int> Datei → Fundstellen (Stand 2026-08-24) */
    private const BASELINE = [
        'resources/views/account/_profile_dialog.blade.php' => 9,
        'resources/views/admin/backup/_restore_test_dialog.blade.php' => 8,
        'resources/views/admin/branding/edit.blade.php' => 4,
        'resources/views/admin/organizations/_compliance.blade.php' => 12,
        'resources/views/admin/organizations/_form_body.blade.php' => 7,
        'resources/views/admin/per-diem-rates/_form_body.blade.php' => 1,
        'resources/views/admin/plugins/_field.blade.php' => 1,
        'resources/views/admin/shift-rotations/index.blade.php' => 2,
        'resources/views/admin/themes/_form_dialog.blade.php' => 1,
        'resources/views/admin/themes/index.blade.php' => 1,
        'resources/views/admin/time-accounts/index.blade.php' => 7,
        'resources/views/agile/backlog.blade.php' => 1,
        'resources/views/agile/board.blade.php' => 10,
        'resources/views/agile/sprints.blade.php' => 2,
        'resources/views/articles/show.blade.php' => 2,
        'resources/views/assignments/_form_body.blade.php' => 1,
        'resources/views/chat/index.blade.php' => 11,
        'resources/views/communication-notes/_form_dialog.blade.php' => 3,
        'resources/views/components/date-range.blade.php' => 1,
        'resources/views/components/facility-picker.blade.php' => 6,
        'resources/views/diary/_form_fields.blade.php' => 2,
        'resources/views/duty-plans/_form.blade.php' => 1,
        'resources/views/events/_form_dialog.blade.php' => 7,
        'resources/views/expenses/_form_body.blade.php' => 1,
        'resources/views/finance/datev/config.blade.php' => 11,
        'resources/views/finance/transfers/_form_dialog.blade.php' => 2,
        'resources/views/foreign-customers/_form_dialog.blade.php' => 13,
        'resources/views/forms/templates/_form_dialog.blade.php' => 6,
        'resources/views/helpdesk/catalog/_item_dialog.blade.php' => 3,
        'resources/views/helpdesk/changes/_form_dialog.blade.php' => 3,
        'resources/views/helpdesk/routing/index.blade.php' => 3,
        'resources/views/ideas/show.blade.php' => 3,
        'resources/views/inventory/counts/index.blade.php' => 1,
        'resources/views/inventory/counts/show.blade.php' => 2,
        'resources/views/inventory/index.blade.php' => 8,
        'resources/views/inventory/lots/index.blade.php' => 2,
        'resources/views/inventory/scan/index.blade.php' => 5,
        'resources/views/legacy/diary/_form_fields.blade.php' => 1,
        'resources/views/legacy/notdienst/_form_dialog.blade.php' => 1,
        'resources/views/legacy/oncall/_form_dialog.blade.php' => 1,
        'resources/views/manufacturing/show.blade.php' => 13,
        'resources/views/org/members/_form.blade.php' => 1,
        'resources/views/payroll/index.blade.php' => 9,
        'resources/views/pricing-margin-rules/index.blade.php' => 1,
        'resources/views/privacy/incidents/show.blade.php' => 1,
        'resources/views/procedures/templates/edit.blade.php' => 9,
        'resources/views/projects/_billable_field.blade.php' => 1,
        'resources/views/projects/_billing_rule_form_dialog.blade.php' => 7,
        'resources/views/projects/_billing_tab.blade.php' => 4,
        'resources/views/projects/_form_dialog.blade.php' => 4,
        'resources/views/projects/_milestone_dialog.blade.php' => 3,
        'resources/views/projects/_recurrence_rule_form_dialog.blade.php' => 14,
        'resources/views/projects/_task_dialog.blade.php' => 13,
        'resources/views/projects/_time_entry_dialog.blade.php' => 10,
        'resources/views/projects/_weather_field.blade.php' => 1,
        'resources/views/purchase-orders/show.blade.php' => 9,
        'resources/views/report-views/index.blade.php' => 2,
        'resources/views/schedule/partials/_shift_dialog.blade.php' => 5,
        'resources/views/schedule/partials/_shift_type_manager.blade.php' => 3,
        'resources/views/shifts/_form_body.blade.php' => 2,
        'resources/views/sick-leaves/_form_dialog.blade.php' => 1,
        'resources/views/sla-contracts/index.blade.php' => 3,
        'resources/views/supplier-catalogs/show.blade.php' => 1,
        'resources/views/tasks/global/_dialog.blade.php' => 10,
        'resources/views/time-approval/correction/_form_dialog.blade.php' => 5,
        'resources/views/time-entries/_admin_form_body.blade.php' => 7,
        'resources/views/timesheets/_entry_form_dialog.blade.php' => 2,
        'resources/views/timesheets/_form_body.blade.php' => 1,
        'resources/views/timesheets/_material_form_dialog.blade.php' => 6,
        'resources/views/timesheets/index.blade.php' => 2,
        'resources/views/users/_contact_fields.blade.php' => 15,
        'resources/views/vacations/_form_dialog.blade.php' => 1,
        'resources/views/vehicles/_form_body.blade.php' => 1,
        'resources/views/work-schedules/_form_body.blade.php' => 4,
    ];

    /** Zählt label.fieldset-label ohne for=, die kein Control umschließen. */
    private function countUnlinkedLabels(string $source): int {
        $count = 0;
        if (preg_match_all('~<label\b[^>]*\bfieldset-label\b[^>]*>~', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return 0;
        }

        foreach ($matches[0] as [$tag, $offset]) {
            if (preg_match('~\bfor=~', $tag) === 1) {
                continue; // explizit verknüpft
            }
            $bodyStart = (int) $offset + strlen($tag);
            $end = strpos($source, '</label>', $bodyStart);
            $body = $end === false ? '' : substr($source, $bodyStart, $end - $bodyStart);
            if (preg_match('~<(?:input|select|textarea)\b|<x-(?:input|select|textarea|checkbox|tag-picker|user-select|project-select)~', $body) === 1) {
                continue; // umschließt sein Control → implizit verknüpft
            }
            $count++;
        }

        return $count;
    }

    public function test_views_do_not_add_unlinked_fieldset_labels(): void {
        $violations = [];
        $stale = [];
        $seen = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            $source = $this->stripBladeComments((string) file_get_contents($file));
            $count = $this->countUnlinkedLabels($source);
            $seen[$relative] = true;

            $allowed = self::BASELINE[$relative] ?? 0;
            if ($count > $allowed) {
                $violations[] = sprintf('%s — %d Fundstelle(n), Baseline erlaubt %d', $relative, $count, $allowed);
            } elseif ($count < $allowed) {
                $stale[] = sprintf("'%s' => %d, // aktuell %d", $relative, $allowed, $count);
            }
        }

        foreach (array_keys(self::BASELINE) as $relative) {
            if (! isset($seen[$relative])) {
                $stale[] = sprintf("'%s' — Datei existiert nicht mehr, Eintrag streichen", $relative);
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Neues <label class=\"fieldset-label\"> ohne for-Verknüpfung (WCAG 1.3.1/3.3.2).\n"
            . "Stattdessen die Feld-Komponenten nutzen (x-input-field/x-select-field/x-textarea-field/\n"
            . "x-checkbox-field/x-date-range) oder label for=\"…\" + id am Control setzen.\n\n"
            . implode("\n", $violations));

        sort($stale);
        $this->assertSame([], $stale, "Baseline abtragen (Fundstellen gesunken/Datei weg) — Einträge in RawFormFieldRuleTest::BASELINE anpassen:\n"
            . implode("\n", $stale));
    }
}
