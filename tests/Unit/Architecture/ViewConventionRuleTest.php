<?php
/*
 * Created on   : Sun Aug 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ViewConventionRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate für View-Konventionen ohne bisheriges Gate (Vollscan
 * 2026-08-23, Welle 1). Ergänzt TableConventionRuleTest (R1–R4) um:
 *
 *  V1  Von/Bis als zwei Datumsfelder statt <x-date-range> (D10/I6)
 *  V2  handgebautes <h1> in App-Views statt <x-page-toolbar> (D16/I9)
 *  V3  <x-table scroll="flex"> ohne Voll-Höhe-Marker bzw. mit Karten
 *      danach — dann greift der Scroll nicht oder quetscht Inhalt weg (I10)
 *  V4  Erfolgs-Flash in der View, obwohl layouts.app ihn bereits rendert (I4)
 *  V5  <x-pagination> auf Index-Seiten ohne `standing` (I18)
 *
 * Altfälle stehen mit Welle-Verweis in den Allow-Listen; neue Views müssen die
 * Konvention erfüllen.
 */
class ViewConventionRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> */
    private const SKIP_PREFIXES = [
        'resources/views/legacy/' => 'Legacy-Modul.',
        'resources/views/vendor/' => 'Fremd-Views.',
        'resources/views/components/' => 'Komponenten definieren die Konventionen.',
        'resources/views/layouts/' => 'Layouts.',
        'resources/views/mail/' => 'Mail-Templates.',
        'resources/views/emails/' => 'Mail-Templates.',
        'resources/views/pdf/' => 'PDF-Templates.',
        'resources/views/errors/' => 'Fehlerseiten.',
    ];

    /** @var array<string, string> V1 */
    private const DATE_RANGE_ALLOW = [
        'resources/views/finance/accounting/_rule_dialog.blade.php' => 'Welle 4 (I6): valid_from/valid_to → x-date-range.',
        'resources/views/admin/maintenance-windows/_form_dialog.blade.php' => 'Welle 4 (I6): starts_at/ends_at (datetime-local).',
        'resources/views/diary/_dispatch_panel.blade.php' => 'Welle 4 (I6): reserved_from/reserved_to.',
        'resources/views/energy-logs/_form_body.blade.php' => 'Welle 4 (I6): started_at/ended_at.',
        'resources/views/per-diem-trips/_form_body.blade.php' => 'Welle 4 (I6): started_at/ended_at.',
        'resources/views/passenger/rides/_form_dialog.blade.php' => 'Welle 4 (I6): window_start/window_end.',
        'resources/views/org/members/_payroll_fields.blade.php' => 'Welle 4 (I6): employment_start_date/_end_date.',
        'resources/views/whistleblowing/public/portal.blade.php' => 'Öffentliches Portal-Layout ohne App-Komponenten (bewusst).',
        'resources/views/rental/calendar.blade.php' => 'Welle 4 (I6): starts_at/ends_at (datetime-local).',
    ];

    /** @var array<string, string> V2 */
    private const HEADING_ALLOW = [
        'resources/views/dashboard/index.blade.php' => 'Begrüßungs-Hero des Dashboards (bewusst, ux-pattern-katalog).',
        'resources/views/chat/index.blade.php' => 'Chat-Kopf mit Kanalwechsel (Eigenlayout).',
        'resources/views/admin/document-design/editor.blade.php' => 'Welle 4 (I9): Editor-Kopf.',
        'resources/views/admin/mail/index.blade.php' => 'Welle 4 (I9).',
        'resources/views/procedures/templates/edit.blade.php' => 'Welle 4 (D16).',
        'resources/views/recipes/menus/index.blade.php' => 'Welle 4 (I9): h1 + Rohkarte → x-page-toolbar/x-card.',
        'resources/views/recipes/menus/show.blade.php' => 'Welle 4 (I9).',
    ];

    /** @var array<string, string> V3 */
    private const SCROLL_FLEX_ALLOW = [
        'resources/views/inventory/index.blade.php' => 'Welle 4 (I10): drei Karten nach der Bestandstabelle.',
        'resources/views/purchase-orders/suggestions.blade.php' => 'Welle 4 (I10): Apply-Formular nach der Tabelle.',
        'resources/views/gaeb/cost-catalogs/show.blade.php' => 'Welle 4 (I10).',
        'resources/views/bill-of-quantities/catalog-assignment.blade.php' => 'Welle 4 (I10).',
        'resources/views/admin/automations/index.blade.php' => 'Welle 4 (I9): Inline-Formular unter scroll=flex-Tabelle.',
    ];

    /** @var array<string, string> V4 — 29 Altfälle, Welle 4 (I4): Block entfernen, Layout rendert den Flash */
    private const FLASH_ALLOW = [
        'resources/views/account/two-factor.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/accounting-migration/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/b2b-catalog/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/b2b-catalog/show.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/backup-targets/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/branding/edit.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/chat/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/cloud-intake/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/cti/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/document-design/editor.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/document-design/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/mail/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/shipments/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/sso/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/terminals/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/time-dimensions/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/admin/wage-type-mappings/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/articles/sales_discount_groups.blade.php' => 'Welle 4 (I4).',
        'resources/views/availability/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/external-contacts/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/payroll/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/products/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/recipes/menus/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/recipes/menus/show.blade.php' => 'Welle 4 (I4).',
        'resources/views/shift-exchanges/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/supplier-catalogs/metal_quotations.blade.php' => 'Welle 4 (I4).',
        'resources/views/whistleblowing/internal/index.blade.php' => 'Welle 4 (I4).',
        'resources/views/whistleblowing/internal/portal.blade.php' => 'Welle 4 (I4).',
        'resources/views/whistleblowing/internal/show.blade.php' => 'Welle 4 (I4).',
    ];

    /** @var list<array{0: string, 1: string}> Von→Bis-Namenspaare (Teilstring-Ersetzung) */
    private const RANGE_PAIRS = [
        ['from', 'to'],
        ['start', 'end'],
        ['started', 'ended'],
        ['starts', 'ends'],
        ['begin', 'end'],
        ['von', 'bis'],
    ];

    public function test_views_follow_the_ui_conventions(): void {
        $violations = [];

        foreach ($this->bladeFiles() as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::SKIP_PREFIXES)) {
                continue;
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));
            $isPartial = str_starts_with(basename($relative), '_');
            $isAppView = str_contains($source, "@extends('layouts.app')") || str_contains($source, '<x-index-page') || str_contains($source, '<x-page-shell');

            // V1 — Von/Bis als zwei Datumsfelder.
            if (! $this->isAllowListed($relative, self::DATE_RANGE_ALLOW)) {
                foreach ($this->dateRangePairs($source) as [$from, $to]) {
                    $violations[] = sprintf('%s  V1 %s/%s als zwei Datumsfelder — <x-date-range> nutzen', $relative, $from, $to);
                }
            }

            // V2 — handgebautes <h1> in App-Views.
            if ($isAppView && ! $this->isAllowListed($relative, self::HEADING_ALLOW)
                && preg_match('/<h1\b/', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d  V2 <h1> handgebaut — <x-page-toolbar>/<x-index-page> nutzen', $relative, $this->lineOf($source, (int) $m[0][1]));
            }

            // V3 — scroll="flex" nur mit Voll-Höhe-Marker und als letztes Element.
            if (! $isPartial && str_contains($source, 'scroll="flex"') && ! $this->isAllowListed($relative, self::SCROLL_FLEX_ALLOW)) {
                if (! str_contains($source, "@section('main-class'")) {
                    $violations[] = sprintf('%s  V3 scroll="flex" ohne @section(\'main-class\', …) — Voll-Höhe greift nicht', $relative);
                }
                $tail = substr($source, (int) strrpos($source, '</x-table>'));
                if (preg_match('/<x-card\b/', $tail) === 1) {
                    $violations[] = sprintf('%s  V3 <x-card> nach der scroll="flex"-Tabelle — Inhalt vor die Tabelle oder scroll entfernen', $relative);
                }
            }

            // V4 — doppelter Erfolgs-Flash.
            if (str_contains($source, "@extends('layouts.app')") && ! $this->isAllowListed($relative, self::FLASH_ALLOW)
                && preg_match('/session\(\s*[\'"]success[\'"]\s*\)/', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
                $violations[] = sprintf('%s:%d  V4 session(\'success\') in der View — layouts.app rendert den Flash bereits', $relative, $this->lineOf($source, (int) $m[0][1]));
            }

            // V5 — Index-Seiten paginieren stehend.
            if (str_ends_with($relative, 'index.blade.php')
                && preg_match('/<x-pagination\b[^>]*>/', $source, $m, PREG_OFFSET_CAPTURE) === 1
                && ! str_contains($m[0][0], 'standing')) {
                $violations[] = sprintf('%s:%d  V5 <x-pagination> ohne standing auf einer Index-Seite', $relative, $this->lineOf($source, (int) $m[0][1]));
            }
        }

        sort($violations);

        $this->assertSame([], $violations, "View-Konvention verletzt (ux-pattern-katalog / Memory-Konventionen):\n\n" . implode("\n", $violations));
    }

    /**
     * Datumsfeld-Namen (rohe <input type="date|datetime-local"> und
     * <x-input-field type="date|datetime-local">) und ihre Von/Bis-Paare.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function dateRangePairs(string $source): array {
        $names = [];
        if (preg_match_all('/<(?:input|x-input-field)\b[^>]*>/s', $source, $tags) > 0) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/\btype="(?:date|datetime-local)"/', $tag) !== 1) {
                    continue;
                }
                if (preg_match('/\bname="([a-z0-9_\[\]]+)"/i', $tag, $n) === 1) {
                    $names[$n[1]] = true;
                }
            }
        }

        $pairs = [];
        foreach (array_keys($names) as $name) {
            foreach (self::RANGE_PAIRS as [$from, $to]) {
                if (! str_contains($name, $from)) {
                    continue;
                }
                $partner = str_replace($from, $to, $name);
                if ($partner !== $name && isset($names[$partner])) {
                    $pairs[$name . '/' . $partner] = [$name, $partner];
                }
            }
        }

        return array_values($pairs);
    }
}
