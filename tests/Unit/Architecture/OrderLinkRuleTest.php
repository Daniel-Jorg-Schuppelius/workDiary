<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : OrderLinkRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „Auftragslinks nur, wenn der Auftrag sich öffnen lässt"
 * (Anlass: UI-Vollcrawl 2026-10-03, Entscheidung des Nutzers 2026-10-04).
 *
 * Team-Ansichten zeigen bewusst auch fremde Aufträge. Ein nackter
 * `route('diary.show', …)`-Link endet dort für Benutzer ohne `diary.viewAny`
 * im 403. Ansichten verlinken deshalb über `<x-order-link :entry="…">`, das
 * den Link nur bei `can('view')` rendert. Ausnahmen stehen unten mit Grund.
 */
class OrderLinkRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Datei → Grund, warum der direkte Link dort stimmt. */
    private const ALLOWED = [
        'resources/views/components/order-link.blade.php' => 'Der Baustein selbst.',
        'resources/views/diary/_entry_card.blade.php' => 'Details-Knopf steht hinter @can(\'view\').',
        'resources/views/diary/_timeline_panel.blade.php' => 'Innerhalb eines geöffneten Auftrags.',
        'resources/views/diary/case-file.blade.php' => 'Innerhalb eines geöffneten Auftrags.',
        'resources/views/dispatch/qualifications.blade.php' => 'Innerhalb eines geöffneten Auftrags.',
        'resources/views/takeoffs/show.blade.php' => 'Rückpfeil hinter Gate::allows(\'view\').',
        'resources/views/dashboard/widgets/open-issues.blade.php' => 'Link hinter Gate::allows(\'view\').',
        'resources/views/dashboard/widgets/recent-comments.blade.php' => 'DashboardService liefert nur Kommentare an eigenen Aufträgen.',
        'resources/views/dashboard/widgets/recent-attachments.blade.php' => 'DashboardService liefert nur Anhänge eigener Aufträge.',
        'resources/views/dashboard/widgets/team-activity.blade.php' => 'Kachel nur für Admins.',
        'resources/views/reports/data-quality.blade.php' => 'Team-Bericht, nur mit report.view.',
        'resources/views/reports/drilldown/asset-protocols.blade.php' => 'Team-Bericht, nur mit report.view.',
        'resources/views/reports/drilldown/customer-protocols.blade.php' => 'Team-Bericht, nur mit report.view.',
        'resources/views/reports/drilldown/entry-type-protocols.blade.php' => 'Team-Bericht, nur mit report.view.',
    ];

    public function test_views_link_orders_through_the_order_link_component(): void {
        $violations = [];
        foreach (array_merge($this->bladeFiles('resources/views'), $this->bladeFiles('app/Plugins')) as $file) {
            $relative = $this->relativePath($file);
            if (isset(self::ALLOWED[$relative])) {
                continue;
            }
            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (preg_match_all('/route\(\s*[\'"]diary\.show[\'"]/', $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as [, $offset]) {
                    $violations[] = $relative . ':' . $this->lineOf($source, (int) $offset);
                }
            }
        }

        $this->assertSame([], $violations, "Auftragslink ohne Sichtprüfung — <x-order-link :entry=\"…\"> nutzen oder Ausnahme begründen:\n" . implode("\n", $violations));
    }

    public function test_allow_list_has_no_stale_entries(): void {
        foreach (array_keys(self::ALLOWED) as $relative) {
            $path = $this->repoRoot() . '/' . $relative;
            $this->assertFileExists($path);
            $this->assertMatchesRegularExpression('/route\(\s*[\'"]diary\.show[\'"]/', (string) file_get_contents($path), $relative . ' verlinkt den Auftrag nicht mehr — Ausnahme entfernen.');
        }
    }
}
