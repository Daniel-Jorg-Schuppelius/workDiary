<?php
/*
 * Created on   : Thu Sep 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : RawEchoRuleTest.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Unit\Architecture\Concerns\ScansSourceTree;

/**
 * Architektur-Gate „ungefiltertes Blade-Echo" (Datenfluss-Audit 2026-09-17):
 * `{!! … !!}` umgeht die Escape-Regel von Blade und ist damit die kürzeste
 * Strecke zu Stored XSS. Erlaubt ist es nur an den hier aufgezählten Stellen,
 * jede mit Begründung — eine neue Datei mit rohem Echo fällt auf und braucht
 * eine bewusste Entscheidung.
 *
 * Die Liste ist bewusst nach Datei geführt (nicht nach Zeile): Zeilen
 * verschieben sich, die Zuständigkeit einer Datei nicht.
 */
class RawEchoRuleTest extends TestCase {
    use ScansSourceTree;

    /** @var array<string, string> Repo-relativer Pfad → Begründung */
    private const ALLOW_LIST = [
        // Vom Server erzeugtes, nicht nutzerbestimmtes Markup
        'resources/views/components/status-badge.blade.php' => 'Icon-Markup aus dem eigenen Icon-Satz.',
        'resources/views/components/empty-state.blade.php' => 'Icon-Markup aus dem eigenen Icon-Satz.',
        'resources/views/components/modal.blade.php' => 'Icon-Markup aus dem eigenen Icon-Satz.',
        'resources/views/components/form-group.blade.php' => 'Icon-Markup aus dem eigenen Icon-Satz.',
        'resources/views/components/sort-th.blade.php' => 'Icon-Markup aus dem eigenen Icon-Satz.',
        'resources/views/components/table.blade.php' => 'Attribut-Bag der Komponente, von Blade selbst erzeugt.',
        'resources/views/layouts/app.blade.php' => 'Theme-CSS und Layout-CSS der Installation (keine Nutzereingabe).',
        'resources/views/components/pdf-layout.blade.php' => 'nl2br(e(...)) — escapt und danach nur Zeilenumbrüche.',
        'resources/views/pdf/timesheet.blade.php' => 'nl2br(e(...)) — escapt und danach nur Zeilenumbrüche.',
        'resources/views/suppliers/show.blade.php' => 'e(...) mit anschließendem Zeilenumbruch-Markup.',
        'resources/views/protocols/pdf.blade.php' => 'Bild-Tag mit e(...) auf dem Pfad.',
        'resources/views/diary/_entry_card.blade.php' => 'Trefferhervorhebung auf bereits escaptem Text.',
        'resources/views/reports/cohort-comparison.blade.php' => 'Zellen-Markup aus einer Closure derselben Ansicht.',
        // Bewusst sanierte bzw. maschinell erzeugte Inhalte
        'resources/views/chat/_message.blade.php' => 'ChatText::render() saniert den Text (SafeHtml-Grenze).',
        'resources/views/help/center/show.blade.php' => 'Hilfetexte werden beim Speichern saniert.',
        'resources/views/mail/invoice.blade.php' => 'Vom Dokument-Renderer erzeugtes Mail-HTML.',
        'resources/views/mail/document.blade.php' => 'Vom Dokument-Renderer erzeugtes Mail-HTML.',
        'resources/views/account/two-factor.blade.php' => 'QR-SVG aus der eigenen TOTP-Bibliothek.',
        'resources/views/customer/two-factor.blade.php' => 'QR-SVG aus der eigenen TOTP-Bibliothek.',
        // JSON-Konfiguration statt HTML
        'resources/views/ideas/show.blade.php' => 'json_encode mit HEX-Flags in einem application/json-Block.',
        'resources/views/plugins/oauth-popup-result.blade.php' => 'json_encode der eigenen Herkunft und des Status.',
        // Plugin-Slots (das Plugin verantwortet sein Markup)
        'resources/views/events/_form_dialog.blade.php' => 'Plugin-Slot.',
        'resources/views/attendances/index.blade.php' => 'Plugin-Slot.',
        'resources/views/invoices/show.blade.php' => 'Plugin-Slot.',
        'resources/views/assets/show.blade.php' => 'Plugin-Slot.',
        'resources/views/customers/show.blade.php' => 'Plugin-Aktionen.',
        // Fremdpaket
        'resources/views/vendor/' => 'Ansichten eines Fremdpakets (l5-swagger).',
    ];

    public function test_raw_blade_echo_stays_on_the_allow_list(): void {
        $violations = [];
        $files = array_merge($this->bladeFiles(), $this->filesUnder('app/Plugins', '/\.blade\.php$/'));

        foreach ($files as $file) {
            $relative = $this->relativePath($file);
            if ($this->isAllowListed($relative, self::ALLOW_LIST)) {
                continue;
            }

            $source = $this->stripBladeComments((string) file_get_contents($file));
            if (preg_match_all('/\{!!/', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
                continue;
            }

            foreach ($matches[0] as [$_, $offset]) {
                $violations[] = sprintf('%s:%d', $relative, $this->lineOf($source, (int) $offset));
            }
        }

        sort($violations);
        $this->assertSame([], $violations, "Ungefiltertes {!! … !!} außerhalb der Allow-List.\n"
            . "Entweder {{ … }} verwenden, den Wert vorher sanieren (SafeHtml-Grenze) oder die Datei mit Begründung aufnehmen.\n\n"
            . implode("\n", $violations));
    }
}
