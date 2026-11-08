<?php
/*
 * Created on   : Wed Oct 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DialogFragmentRedirectRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Feature\Routing;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Gate (Issue #106): Eine Aktion leitet nicht auf eine Route weiter, die nur
 * ein Dialog-Fragment rendert. Der Browser landete dort auf HTML ohne Layout;
 * heute fängt RenderDialogFragmentAsPage das ab, gemeint war aber die Liste
 * oder ein Folgedialog (with('open_dialog', …) bzw. data-entry-modal-stack).
 *
 * Zweite Regel (Befund 2026-11-03, Rückfragen-Liste): Ein Link einer View auf
 * eine solche Route trägt data-entry-modal-trigger, sonst navigiert der
 * Browser und zeigt das Fragment als eigene Seite im Dialog-Stil.
 */
class DialogFragmentRedirectRuleTest extends TestCase {
    /**
     * Weiterleitung auf den eigenen Dialog: das Formular sitzt in genau
     * diesem Dialog (data-entry-form), der Dialog-Host lädt ihn neu (stay).
     *
     * @var array<string, string> "Controller-Datei => Route" → Grund
     */
    private const array ALLOWED = [
        'Platform/OrganizationController.php => admin.organizations.edit' => 'Org-Admin speichert im eigenen Organisations-Dialog',
        'Crisis/CrisisStatusPageController.php => crisis.status-page.edit' => 'Token-Aktionen im eigenen Dialog, neuer Link nur dort sichtbar',
        'Investments/InvestmentProposalController.php => investments.proposals.link' => 'Token-Aktionen im eigenen Dialog, neuer Link nur dort sichtbar',
        'Inventory/SerialPassportSettingsController.php => serials.passport.edit' => 'Token-Aktionen im eigenen Dialog, neuer Link nur dort sichtbar',
        'Sustainability/SustainabilityExcerptController.php => sustainability.excerpt.edit' => 'Veröffentlichen und Token-Aktionen im eigenen Dialog',
    ];

    public function test_actions_do_not_redirect_to_dialog_fragment_routes(): void {
        $fragments = $this->fragmentRoutes();
        $this->assertNotEmpty($fragments, 'Sonde findet keine Fragment-Routen mehr — Erkennung prüfen');

        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Http/Controllers')));
        foreach ($files as $file) {
            if (! str_ends_with((string) $file, '.php')) {
                continue;
            }
            $source = (string) file_get_contents((string) $file);
            preg_match_all("/(?:redirect\\(\\)->route|to_route|redirect\\(route|->toList)\\(\\s*'([a-z0-9_.\\-]+)'/i", $source, $matches);
            $relative = Str::after(str_replace('\\', '/', (string) $file), 'Http/Controllers/');
            foreach (array_unique($matches[1]) as $name) {
                if (isset($fragments[$name]) && ! isset(self::ALLOWED[$relative . ' => ' . $name])) {
                    $found[] = $relative . ' => ' . $name;
                }
            }
        }

        $this->assertSame([], $found, "Weiterleitung auf ein Dialog-Fragment — auf die Liste leiten und den Dialog per with('open_dialog', route(…)) öffnen:\n" . implode("\n", $found));
    }

    /**
     * Links auf Fragment-Routen, die bewusst ohne Trigger stehen.
     *
     * @var array<string, string> "View => Route" → Grund
     */
    private const array LINK_ALLOWED = [
        'week/index.blade.php => diary.create' => 'URL-Vorlage für den Kalender, app.js öffnet den Dialog selbst',
        'week/index.blade.php => shifts.create' => 'URL-Vorlage für den Kalender, app.js öffnet den Dialog selbst',
        'week/index.blade.php => assignments.create' => 'URL-Vorlage für den Kalender, app.js öffnet den Dialog selbst',
        'ideas/show.blade.php => ideas.nodes.comments' => 'URL-Vorlage für die Ideenkarte, das Skript öffnet den Dialog',
        'ideas/show.blade.php => ideas.nodes.attachments' => 'URL-Vorlage für die Ideenkarte, das Skript öffnet den Dialog',
        'ideas/show.blade.php => ideas.nodes.link.form' => 'URL-Vorlage für die Ideenkarte, das Skript öffnet den Dialog',
        'billing/feed.blade.php => expenses.receipt' => 'Linkkarte mit Modal-Kennzeichen, die Zeile setzt den Trigger aus dem Kennzeichen',
        'finance/resale/_plan_section.blade.php => finance.resale.vouchers.show' => 'Hilfsfunktion für Beleglinks, die Verwendungen tragen den Trigger',
    ];

    /** Marker im Umfeld eines Links, die den Dialog-Host oder einen eigenen Öffner belegen. */
    private const string LINK_MARKERS = '/data-entry-modal-(?:trigger|autoopen|stack)|open_dialog|_dialog_url|\$dialogUrl|\?dialog=1|\'dialog\' => 1|\'modal\' => true'
        . '|<x-(?:collection-add-button|ui-action|entity-header|filter-bar|order-link|content-references|damage-cases-card|resale[.-]holder|billing\.document-relations|help-drawer|maintenance-banner)\b/';

    public function test_view_links_to_dialog_fragments_carry_the_modal_trigger(): void {
        $fragments = $this->fragmentRoutes();
        $found = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', (string) $file);
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }
            $relative = Str::after($path, 'resources/views/');
            // Dialoge nennen ihre eigene URL; Mails, PDFs und Druckseiten haben keinen Dialog-Host.
            if (preg_match('#(^|/)_[^/]*dialog[^/]*\.blade\.php$#', $relative) === 1
                || preg_match('#^(mail|emails|pdf|print)/|/(emails|pdf|print)/#', $relative) === 1) {
                continue;
            }
            $lines = file($path) ?: [];
            foreach ($lines as $index => $line) {
                if (preg_match_all("/route\(\s*'([a-z0-9_.\-]+)'/", $line, $matches) === 0) {
                    continue;
                }
                $context = implode('', array_slice($lines, max(0, $index - 4), 9));
                foreach (array_unique($matches[1]) as $name) {
                    if (! isset($fragments[$name]) || isset(self::LINK_ALLOWED[$relative . ' => ' . $name])) {
                        continue;
                    }
                    if (preg_match(self::LINK_MARKERS, $context) === 1 || preg_match('/<form\b|:action=|\baction=/', $line) === 1) {
                        continue;
                    }
                    $found[] = $relative . ':' . ($index + 1) . ' => ' . $name;
                }
            }
        }

        $this->assertSame([], $found, "Link auf ein Dialog-Fragment ohne data-entry-modal-trigger — der Browser zeigt das Fragment sonst als eigene Seite:\n" . implode("\n", $found));
    }

    /** @return array<string, true> GET-Routen, deren Action nur ein `…._*dialog*`-Fragment rendert. */
    private function fragmentRoutes(): array {
        $fragments = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            $action = $route->getActionName();
            if ($name === null || ! in_array('GET', $route->methods(), true) || ! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action);
            if (! method_exists($class, $method)) {
                continue;
            }
            $reflection = new ReflectionMethod($class, $method);
            $lines = file((string) $reflection->getFileName()) ?: [];
            $body = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
            if (preg_match("/view\\(\\s*'[^']*\\._[a-z_]*dialog[a-z_]*'/", $body) === 1
                && preg_match("/boolean\\('dialog'\\)|->ajax\\(\\)/", $body) !== 1) {
                $fragments[$name] = true;
            }
        }

        return $fragments;
    }
}
