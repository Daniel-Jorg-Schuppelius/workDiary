<?php
/*
 * Created on   : Mon Aug 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TextContrastRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Textkontrast" (Vollscan 2026-08-23, I2 / WCAG 1.4.3 /
 * BFSG): text-base-content mit /30–/50 unterschreitet in den daisyUI-Themes
 * (corporate/dim) deutlich 4,5:1 — für Text unzulässig. Der semantische
 * Token `.text-muted` (resources/css/app.css, 70 % base-content) ersetzt
 * diese Abstufungen; die Komponenten (resources/views/components/**) sind
 * bereits vollständig umgestellt und dürfen nicht zurückfallen.
 *
 * Regel: Kein neues text-base-content/30, /40 oder /50 in resources/views.
 * Der Bestand steht als BASELINE (Datei → Anzahl Fundstellen) und wird
 * abgetragen — sinkt die Zahl, MUSS der Eintrag hier reduziert/gestrichen
 * werden; steigen darf sie nie.
 *
 * Nächste Stufe (bewusst noch nicht im Gate): text-base-content/60
 * (~1449 Stellen, corporate/dim → 3,88:1 dunkel) ebenfalls verbieten,
 * sobald die /30–/50-Baseline abgetragen ist — Ersatz ist auch dort
 * `text-muted`.
 */
class TextContrastRuleTest extends TestCase {
    use ScansSourceTree;

    private const PATTERN = '~text-base-content/(?:30|40|50)\b~';

    /** @var array<string, int> Datei → Fundstellen (Stand 2026-08-24) */
    private const BASELINE = [
        'resources/views/account/_profile_dialog.blade.php' => 1,
        'resources/views/account/two-factor.blade.php' => 1,
        'resources/views/admin/access/_permission_matrix.blade.php' => 1,
        'resources/views/admin/access/members/_form_dialog.blade.php' => 1,
        'resources/views/admin/accounting-migration/index.blade.php' => 1,
        'resources/views/admin/ai/_capability_dialog.blade.php' => 1,
        'resources/views/admin/ai/_preview_dialog.blade.php' => 3,
        'resources/views/admin/audit-diff/index.blade.php' => 2,
        'resources/views/admin/backup-targets/index.blade.php' => 1,
        'resources/views/admin/backup/status.blade.php' => 3,
        'resources/views/admin/chat/index.blade.php' => 1,
        'resources/views/admin/classification-requirements/index.blade.php' => 3,
        'resources/views/admin/components/index.blade.php' => 4,
        'resources/views/admin/cti/index.blade.php' => 1,
        'resources/views/admin/data/index.blade.php' => 3,
        'resources/views/admin/diagnostics/index.blade.php' => 2,
        'resources/views/admin/document-design/_profile_form_dialog.blade.php' => 2,
        'resources/views/admin/document-design/editor.blade.php' => 9,
        'resources/views/admin/integration/inbox.blade.php' => 4,
        'resources/views/admin/integrity/index.blade.php' => 4,
        'resources/views/admin/license/index.blade.php' => 7,
        'resources/views/admin/license/issuer.blade.php' => 3,
        'resources/views/admin/maintenance-windows/index.blade.php' => 1,
        'resources/views/admin/metrics/index.blade.php' => 10,
        'resources/views/admin/notification-rules/index.blade.php' => 1,
        'resources/views/admin/operations/index.blade.php' => 2,
        'resources/views/admin/organizations/index.blade.php' => 3,
        'resources/views/admin/plugin-errors/index.blade.php' => 1,
        'resources/views/admin/plugin-errors/show.blade.php' => 1,
        'resources/views/admin/plugins/_form_dialog.blade.php' => 1,
        'resources/views/admin/plugins/index.blade.php' => 3,
        'resources/views/admin/privacy/index.blade.php' => 1,
        'resources/views/admin/report-targets/_form_dialog.blade.php' => 2,
        'resources/views/admin/scheduler/index.blade.php' => 3,
        'resources/views/admin/scope/index.blade.php' => 1,
        'resources/views/admin/security/index.blade.php' => 8,
        'resources/views/admin/sessions/index.blade.php' => 10,
        'resources/views/admin/settings/index.blade.php' => 1,
        'resources/views/admin/shipments/index.blade.php' => 1,
        'resources/views/admin/webhooks/_form_dialog.blade.php' => 1,
        'resources/views/agile/backlog.blade.php' => 1,
        'resources/views/agile/board.blade.php' => 1,
        'resources/views/agile/reports/overview.blade.php' => 1,
        'resources/views/agile/sprints.blade.php' => 1,
        'resources/views/articles/duplicates.blade.php' => 4,
        'resources/views/articles/merge-compare.blade.php' => 5,
        'resources/views/assets/_defect_form_dialog.blade.php' => 1,
        'resources/views/assets/components/index.blade.php' => 1,
        'resources/views/assets/merge-compare.blade.php' => 3,
        'resources/views/assets/show.blade.php' => 2,
        'resources/views/attachments/_panel.blade.php' => 1,
        'resources/views/auth/register.blade.php' => 2,
        'resources/views/auth/two-factor-challenge.blade.php' => 1,
        'resources/views/billing/feed.blade.php' => 3,
        'resources/views/cash-registers/show.blade.php' => 3,
        'resources/views/chat/_channel_list.blade.php' => 1,
        'resources/views/chat/_message.blade.php' => 2,
        'resources/views/chat/index.blade.php' => 1,
        'resources/views/claims/show.blade.php' => 3,
        'resources/views/comments/_thread.blade.php' => 1,
        'resources/views/contracts/show.blade.php' => 1,
        'resources/views/coverage-requirements/_heatmap.blade.php' => 1,
        'resources/views/coverage-requirements/index.blade.php' => 2,
        'resources/views/customer-queries/index.blade.php' => 2,
        'resources/views/customer/dashboard.blade.php' => 1,
        'resources/views/customer/documents/index.blade.php' => 1,
        'resources/views/customer/queries/index.blade.php' => 1,
        'resources/views/customer/tickets/show.blade.php' => 1,
        'resources/views/customer/two-factor-challenge.blade.php' => 1,
        'resources/views/customer/two-factor.blade.php' => 1,
        'resources/views/customers/_billing_panel.blade.php' => 1,
        'resources/views/customers/_domains_panel.blade.php' => 1,
        'resources/views/customers/_material_panel.blade.php' => 2,
        'resources/views/customers/_portal_access_panel.blade.php' => 1,
        'resources/views/customers/_project_list_items.blade.php' => 1,
        'resources/views/customers/duplicates.blade.php' => 4,
        'resources/views/customers/index.blade.php' => 1,
        'resources/views/customers/merge-compare.blade.php' => 5,
        'resources/views/customers/show.blade.php' => 7,
        'resources/views/dashboard/index.blade.php' => 5,
        'resources/views/diary/_form_fields.blade.php' => 1,
        'resources/views/diary/_lifecycle_panel.blade.php' => 1,
        'resources/views/diary/_procedure_panel.blade.php' => 2,
        'resources/views/diary/case-file.blade.php' => 2,
        'resources/views/dispatch/board.blade.php' => 2,
        'resources/views/disposal/show.blade.php' => 2,
        'resources/views/documents/index.blade.php' => 1,
        'resources/views/domain/reseller/index.blade.php' => 1,
        'resources/views/domain/reseller/show.blade.php' => 1,
        'resources/views/domain/show.blade.php' => 2,
        'resources/views/duties/_tab_krank.blade.php' => 1,
        'resources/views/duty-plans/show.blade.php' => 1,
        'resources/views/errors/_page.blade.php' => 1,
        'resources/views/expenses/inbox.blade.php' => 1,
        'resources/views/expenses/index.blade.php' => 1,
        'resources/views/finance/accounting/accounts.blade.php' => 1,
        'resources/views/finance/reconciliation/show.blade.php' => 2,
        'resources/views/finance/transfers/show.blade.php' => 5,
        'resources/views/foreign-customers/show.blade.php' => 6,
        'resources/views/forms/_panel.blade.php' => 1,
        'resources/views/guarantees/index.blade.php' => 1,
        'resources/views/helpdesk/board/index.blade.php' => 1,
        'resources/views/helpdesk/catalog/index.blade.php' => 3,
        'resources/views/holidays/_form_dialog.blade.php' => 1,
        'resources/views/holidays/index.blade.php' => 1,
        'resources/views/inventory/scan/index.blade.php' => 1,
        'resources/views/invoices/_preview.blade.php' => 1,
        'resources/views/invoices/show.blade.php' => 1,
        'resources/views/invoicing/_text_correction_learn.blade.php' => 1,
        'resources/views/isms/_soa_dialog.blade.php' => 2,
        'resources/views/isms/audit-programs/index.blade.php' => 1,
        'resources/views/isms/audits/index.blade.php' => 1,
        'resources/views/isms/conformity/index.blade.php' => 3,
        'resources/views/isms/controls/index.blade.php' => 2,
        'resources/views/isms/csf/crosswalk.blade.php' => 1,
        'resources/views/isms/csf/dashboard.blade.php' => 2,
        'resources/views/isms/dashboard.blade.php' => 2,
        'resources/views/isms/incidents/index.blade.php' => 1,
        'resources/views/isms/packages/index.blade.php' => 2,
        'resources/views/isms/readiness.blade.php' => 3,
        'resources/views/isms/requirements/index.blade.php' => 3,
        'resources/views/isms/reviews/index.blade.php' => 1,
        'resources/views/isms/risks/index.blade.php' => 2,
        'resources/views/isms/software/index.blade.php' => 2,
        'resources/views/isms/suppliers/index.blade.php' => 2,
        'resources/views/kanban/index.blade.php' => 1,
        'resources/views/key-handovers/index.blade.php' => 2,
        'resources/views/knowledge/_context_card.blade.php' => 2,
        'resources/views/knowledge/_form_dialog.blade.php' => 2,
        'resources/views/knowledge/index.blade.php' => 1,
        'resources/views/knowledge/show.blade.php' => 1,
        'resources/views/layouts/app.blade.php' => 2,
        'resources/views/layouts/install.blade.php' => 1,
        'resources/views/legacy/archive/_show_body.blade.php' => 2,
        'resources/views/legacy/callcenter/notdienst.blade.php' => 15,
        'resources/views/legacy/diary/_show_body.blade.php' => 2,
        'resources/views/legacy/users/_form_dialog.blade.php' => 1,
        'resources/views/legal/show.blade.php' => 1,
        'resources/views/meter-readings/index.blade.php' => 1,
        'resources/views/notifications/index.blade.php' => 1,
        'resources/views/onboarding/index.blade.php' => 1,
        'resources/views/partials/global-search.blade.php' => 3,
        'resources/views/patrols/run.blade.php' => 1,
        'resources/views/payroll/index.blade.php' => 1,
        'resources/views/presence/board.blade.php' => 3,
        'resources/views/privacy/compliance/index.blade.php' => 1,
        'resources/views/procedures/runs/show.blade.php' => 4,
        'resources/views/procedures/templates/_recipe.blade.php' => 1,
        'resources/views/procedures/templates/edit.blade.php' => 1,
        'resources/views/procedures/templates/index.blade.php' => 1,
        'resources/views/projects/_billing_tab.blade.php' => 2,
        'resources/views/projects/_overview_tab.blade.php' => 4,
        'resources/views/projects/_picker_dialog.blade.php' => 4,
        'resources/views/projects/_recurrence_rule_form_dialog.blade.php' => 3,
        'resources/views/projects/_recurrence_tab.blade.php' => 1,
        'resources/views/projects/_task_row.blade.php' => 2,
        'resources/views/projects/_tasks_tab.blade.php' => 2,
        'resources/views/projects/_time_tab.blade.php' => 1,
        'resources/views/projects/_timeline_tab.blade.php' => 1,
        'resources/views/projects/duplicates.blade.php' => 4,
        'resources/views/projects/index.blade.php' => 1,
        'resources/views/projects/merge-compare.blade.php' => 5,
        'resources/views/projects/planning.blade.php' => 2,
        'resources/views/protocols/show.blade.php' => 1,
        'resources/views/public/audit-package.blade.php' => 2,
        'resources/views/public/protocol-sign.blade.php' => 2,
        'resources/views/reports/absence-card.blade.php' => 1,
        'resources/views/reports/allocations.blade.php' => 1,
        'resources/views/reports/coverage.blade.php' => 2,
        'resources/views/reports/customer-project.blade.php' => 4,
        'resources/views/reports/drilldown/asset-recurring-defects.blade.php' => 1,
        'resources/views/reports/entry-types.blade.php' => 1,
        'resources/views/reports/expenses.blade.php' => 1,
        'resources/views/reports/external-payouts.blade.php' => 2,
        'resources/views/reports/month-by-user-team.blade.php' => 2,
        'resources/views/reports/my-month.blade.php' => 3,
        'resources/views/reports/plan-ist/presence.blade.php' => 1,
        'resources/views/reports/plan-ist/projects.blade.php' => 2,
        'resources/views/reports/plan-ist/shifts.blade.php' => 1,
        'resources/views/reports/presence-emergency.blade.php' => 1,
        'resources/views/reports/project-details.blade.php' => 2,
        'resources/views/reports/qualifications.blade.php' => 1,
        'resources/views/reports/supplier-scorecards/index.blade.php' => 5,
        'resources/views/reports/supplier-scorecards/show.blade.php' => 9,
        'resources/views/reports/surcharge-forecast.blade.php' => 1,
        'resources/views/reports/week-by-user.blade.php' => 3,
        'resources/views/rooms/_form_dialog.blade.php' => 1,
        'resources/views/safety-events/show.blade.php' => 3,
        'resources/views/schedule/import/preview.blade.php' => 2,
        'resources/views/schedule/partials/_calendar_cell.blade.php' => 2,
        'resources/views/schedule/partials/_week_matrix.blade.php' => 3,
        'resources/views/sick-leaves/_form_dialog.blade.php' => 1,
        'resources/views/suppliers/duplicates.blade.php' => 4,
        'resources/views/suppliers/merge-compare.blade.php' => 5,
        'resources/views/sustainability/index.blade.php' => 1,
        'resources/views/time-accounts/index.blade.php' => 1,
        'resources/views/timesheets/_adopt_dialog.blade.php' => 1,
        'resources/views/timesheets/show.blade.php' => 1,
        'resources/views/today/_quick_book.blade.php' => 3,
        'resources/views/today/show.blade.php' => 1,
        'resources/views/tours/edit.blade.php' => 3,
        'resources/views/week/index.blade.php' => 1,
    ];

    public function test_views_do_not_add_low_contrast_text_classes(): void {
        $violations = [];
        $stale = [];
        $seen = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            $source = $this->stripBladeComments((string) file_get_contents($file));
            $count = (int) preg_match_all(self::PATTERN, $source);
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
        $this->assertSame([], $violations, "Neuer Text mit text-base-content/30–/50 (WCAG 1.4.3 unter 4,5:1).\n"
            . "Stattdessen `text-muted` (semantischer Token, resources/css/app.css) verwenden;\n"
            . "reine Deko-Elemente ohne Textinformation bekommen aria-hidden statt einer Ausnahme.\n\n"
            . implode("\n", $violations));

        sort($stale);
        $this->assertSame([], $stale, "Baseline abtragen (Fundstellen gesunken/Datei weg) — Einträge in TextContrastRuleTest::BASELINE anpassen:\n"
            . implode("\n", $stale));
    }
}
