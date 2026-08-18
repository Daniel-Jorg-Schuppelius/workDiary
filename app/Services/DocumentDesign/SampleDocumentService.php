<?php
/*
 * Created on   : Fri Jul 11 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SampleDocumentService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\DocumentDesign;

use App\Enums\DocumentDesign\{InformationBlock, RenderDocumentFamily, RenderDocumentKind};
use App\Models\Organization;
use CommonToolkit\Helper\Data\NumberHelper;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

/**
 * Testdokumente je Dokumentart (MVP-302; Ausbau #83): artspezifische
 * Beispieldaten (Angebot/Rechnung/Mahnung mit Positionen, Bericht/Protokoll
 * mit Fließtext, Stundenzettel mit Zeitentabelle, Formular mit Feldliste)
 * und umschaltbare Szenarien — lange Texte, viele Tabellenzeilen, mehrere
 * Steuersätze, Seitenumbruch — direkt aus dem Editor als PDF abrufbar,
 * ohne echte Belegdaten zu benötigen.
 */
class SampleDocumentService {
    public const SCENARIO_STANDARD = 'standard';

    public const SCENARIO_LONG_TEXT = 'long_text';

    public const SCENARIO_MANY_ROWS = 'many_rows';

    public const SCENARIO_MULTI_TAX = 'multi_tax';

    /** @var array<int, string> */
    public const SCENARIOS = [self::SCENARIO_STANDARD, self::SCENARIO_LONG_TEXT, self::SCENARIO_MANY_ROWS, self::SCENARIO_MULTI_TAX];

    public function __construct(private readonly DocumentDesignRenderer $renderer) {}

    /** @param array<string, mixed>|null $payload Explizites Payload (z. B. Entwurf) statt aktives Profil. */
    public function pdf(Organization $organization, RenderDocumentKind $kind, ?array $payload = null, string $scenario = self::SCENARIO_STANDARD): string {
        $payload ??= $this->renderer->payloadFor($organization, $kind);
        $html = $this->renderer->compose($this->sampleHtml($organization, $kind, $payload, $scenario), $payload);

        return PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('Test-PDF-Erzeugung fehlgeschlagen.');
    }

    /** Anzeigename eines Szenarios (Editor-Auswahl). */
    public static function scenarioLabel(string $scenario): string {
        return match ($scenario) {
            self::SCENARIO_LONG_TEXT => (string) __('Lange Texte'),
            self::SCENARIO_MANY_ROWS => (string) __('Viele Positionen (Seitenumbruch)'),
            self::SCENARIO_MULTI_TAX => (string) __('Mehrere Steuersätze'),
            default => (string) __('Standard'),
        };
    }

    /** @param array<string, mixed>|null $payload */
    public function sampleHtml(Organization $organization, RenderDocumentKind $kind, ?array $payload, string $scenario = self::SCENARIO_STANDARD): string {
        $design = $this->renderer->context($payload);
        $scenario = in_array($scenario, self::SCENARIOS, true) ? $scenario : self::SCENARIO_STANDARD;
        $long = str_repeat((string) __('Dieser Beispieltext prüft Zeilenumbruch, Silbentrennung und Blocksatzverhalten im gewählten Tabellenstil. '), $scenario === self::SCENARIO_LONG_TEXT ? 8 : 3);

        $blocks = '';
        if ($design->show(InformationBlock::SenderLine)) {
            $style = $design->senderLineStyle();
            $blocks .= '<div' . ($style !== null ? ' style="' . $style . '"' : ' style="font-size:7px; text-decoration: underline;"') . '>'
                . e($organization->name) . ' · ' . __('Musterstraße 1 · 12345 Musterstadt') . '</div>';
        }
        if ($design->show(InformationBlock::RecipientAddress)) {
            $style = $design->addressWindowStyle();
            $blocks .= '<div' . ($style !== null ? ' style="' . $style . '"' : '') . '><strong>' . __('Beispiel GmbH') . '</strong><br>'
                . __('Frau Erika Mustermann') . '<br>' . __('Beispielweg 12') . '<br>98765 ' . __('Beispielstadt') . '</div>';
        }

        $identity = $design->show(InformationBlock::CompanyIdentity)
            ? '<p style="font-size: 9px; color: #555;">' . e($organization->name) . ' · ' . __('Geschäftsführung: Max Mustermann · Amtsgericht Musterstadt HRB 12345') . '</p>'
            : '';
        $tax = $design->show(InformationBlock::TaxIdentity)
            ? '<p style="font-size: 9px; color: #555;">' . __('USt-IdNr.: DE123456789 · Steuernummer: 12/345/67890') . '</p>'
            : '';
        $bank = $design->show(InformationBlock::BankDetails)
            ? '<p style="font-size: 9px; color: #555;">' . __('Musterbank · IBAN DE02 1203 0000 0000 2020 51 · BIC BYLADEM1001') . '</p>'
            : '';
        $intro = $design->show(InformationBlock::IntroText) ? '<p>' . $this->introText($kind, $long) . '</p>' : '';
        $closing = $design->show(InformationBlock::ClosingText) ? '<p>' . $long . '</p>' : '';
        $confidential = $design->show(InformationBlock::Confidentiality)
            ? '<p style="font-size: 8px; color: #999;">' . __('Vertraulich — nur für den benannten Empfänger bestimmt.') . '</p>'
            : '';

        $meta = $design->show(InformationBlock::DocumentMeta)
            ? '<h1>' . $kind->label() . ' TEST-0001</h1><p>' . $this->metaLine($kind) . '</p>'
            : '';

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . __('Testdokument') . '</title>'
            . '<style>'
            . "body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }\n"
            . "h1 { font-size: 18px; margin: 0 0 4px; }\n"
            . "table { border-collapse: collapse; width: 100%; margin-top: 12px; }\n"
            . "th, td { padding: 4px 6px; border-bottom: 1px solid #ccc; text-align: left; }\n"
            . "td.num, th.num { text-align: right; }\n"
            . '@page { margin: 20mm; }'
            . '</style></head><body>'
            . $blocks
            . '<div style="margin-top: ' . ($design->hasProfile() && $design->addressWindowStyle() !== null ? '45mm' : '8mm') . ';">'
            . $meta
            . $intro
            . $this->bodyFor($kind, $design, $scenario, $long)
            . $closing
            . $identity . $tax . $bank . $confidential
            . '</div></body></html>';
    }

    /** Artspezifische Meta-Zeile (Angebotsgültigkeit, Mahnstufe, Zeitraum …). */
    private function metaLine(RenderDocumentKind $kind): string {
        $base = __('Datum') . ': 01.07.2026 · ' . __('Referenz') . ': PRJ-42';

        return match ($kind) {
            RenderDocumentKind::Quote => $base . ' · ' . __('Gültig bis') . ': 31.07.2026',
            RenderDocumentKind::Dunning => $base . ' · ' . __('Mahnstufe') . ': 2 · ' . __('Zahlbar bis') . ': 15.07.2026',
            RenderDocumentKind::Timesheet => $base . ' · ' . __('Zeitraum') . ': 01.06.–30.06.2026',
            default => $base . ' · ' . __('Leistungszeitraum') . ': 01.06.–30.06.2026',
        };
    }

    private function introText(RenderDocumentKind $kind, string $long): string {
        return match ($kind) {
            RenderDocumentKind::Quote => (string) __('Vielen Dank für Ihre Anfrage — gerne bieten wir Ihnen die folgenden Leistungen an.'),
            RenderDocumentKind::OrderConfirmation => (string) __('Hiermit bestätigen wir Ihren Auftrag mit den folgenden Positionen.'),
            RenderDocumentKind::Dunning => (string) __('Trotz Fälligkeit konnten wir zu den folgenden Belegen noch keinen Zahlungseingang feststellen. Bitte gleichen Sie die offenen Beträge bis zum genannten Termin aus.'),
            default => $long,
        };
    }

    /** Artspezifischer Dokumentkörper. */
    private function bodyFor(RenderDocumentKind $kind, DesignContext $design, string $scenario, string $long): string {
        if ($kind === RenderDocumentKind::Dunning) {
            return $this->dunningTable($design);
        }
        if ($kind === RenderDocumentKind::Timesheet) {
            return $this->timesheetTable($design, $scenario);
        }
        if ($kind === RenderDocumentKind::Form) {
            return $this->formTable($long);
        }
        if ($kind === RenderDocumentKind::Label) {
            return '<div style="border: 1px solid #ccc; padding: 6px; width: 60mm;">'
                . '<strong>INV-2026-0042</strong><br>' . __('Lagerplatz A-03-17') . '</div>';
        }
        if ($kind->family() === RenderDocumentFamily::Evidence) {
            return $this->evidenceBody($scenario, $long);
        }

        // Vertrieb/Einkauf: Positionstabelle mit Rabatten, Summen und Steuern.
        return $this->itemsTable($design, $scenario, $long);
    }

    private function itemsTable(DesignContext $design, string $scenario, string $long): string {
        $count = match ($scenario) {
            self::SCENARIO_MANY_ROWS => 60,
            self::SCENARIO_LONG_TEXT => 8,
            default => 32,
        };
        $taxRates = $scenario === self::SCENARIO_MULTI_TAX
            ? [['19 %', 0.5, 0.19], ['7 %', 0.3, 0.07], ['0 %', 0.2, 0.0]]
            : [['19 %', 0.7, 0.19], ['7 %', 0.3, 0.07]];

        $rows = '';
        $net = 0.0;
        for ($i = 1; $i <= $count; $i++) {
            $qty = $i % 7 + 1;
            $price = round(11.9 + $i * 3.37, 2);
            $discount = $i % 5 === 0 ? 10 : 0;
            $sum = round($qty * $price * (1 - $discount / 100), 2);
            $net += $sum;
            $longEvery = $scenario === self::SCENARIO_LONG_TEXT ? 2 : 9;
            $rows .= sprintf(
                '<tr><td>%d</td><td>%s%s</td><td class="num">%d</td><td class="num">%.2f €</td><td class="num">%s</td><td class="num">%.2f €</td></tr>',
                $i,
                __('Beispielposition :n', ['n' => $i]),
                $i % $longEvery === 0 ? '<br><span style="font-size: 90%">' . $long . '</span>' : '',
                $qty,
                $price,
                $discount > 0 ? $discount . ' %' : '—',
                $sum,
            );
        }

        $taxRows = '';
        $taxTotal = 0.0;
        foreach ($taxRates as [$label, $share, $rate]) {
            $amount = round($net * $share * $rate, 2);
            $taxTotal += $amount;
            $taxRows .= '<tr><td colspan="5" class="num">' . __('USt.') . ' ' . $label . '</td><td class="num">' . NumberHelper::toGermanFormat($amount, 2, withThousandsSeparator: true) . ' €</td></tr>';
        }

        return '<table><thead><tr><th>' . __('Pos.') . '</th><th>' . __('Bezeichnung') . '</th><th class="num">' . __('Menge') . '</th><th class="num">' . __('Einzelpreis') . '</th><th class="num">' . __('Rabatt') . '</th><th class="num">' . __('Summe') . '</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . ($design->show(InformationBlock::Totals) ? '<tfoot>'
                . '<tr><td colspan="5" class="num">' . __('Zwischensumme (netto)') . '</td><td class="num">' . NumberHelper::toGermanFormat($net, 2, withThousandsSeparator: true) . ' €</td></tr>'
                . ($design->show(InformationBlock::TaxBreakdown) ? $taxRows : '')
                . '<tr><td colspan="5" class="num">' . __('Gesamtbetrag') . '</td><td class="num">' . NumberHelper::toGermanFormat($net + $taxTotal, 2, withThousandsSeparator: true) . ' €</td></tr>'
                . '</tfoot>' : '')
            . '</table>';
    }

    /** Mahnung: offene Belege mit Mahngebühr und Zahlungsziel. */
    private function dunningTable(DesignContext $design): string {
        $items = [
            ['RE-2026-0101', '02.05.2026', '16.05.2026', 1785.00],
            ['RE-2026-0117', '20.05.2026', '03.06.2026', 428.40],
            ['RE-2026-0129', '01.06.2026', '15.06.2026', 2261.00],
        ];
        $rows = '';
        $total = 0.0;
        foreach ($items as [$number, $date, $due, $amount]) {
            $total += $amount;
            $rows .= '<tr><td>' . $number . '</td><td>' . $date . '</td><td>' . $due . '</td><td class="num">'
                . NumberHelper::toGermanFormat($amount, 2, withThousandsSeparator: true) . ' €</td></tr>';
        }
        $fee = 12.50;

        return '<table><thead><tr><th>' . __('Beleg') . '</th><th>' . __('Datum') . '</th><th>' . __('Fällig seit') . '</th><th class="num">' . __('Offener Betrag') . '</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . ($design->show(InformationBlock::Totals) ? '<tfoot>'
                . '<tr><td colspan="3" class="num">' . __('Mahngebühr') . '</td><td class="num">' . NumberHelper::toGermanFormat($fee, 2, withThousandsSeparator: true) . ' €</td></tr>'
                . '<tr><td colspan="3" class="num">' . __('Gesamtforderung') . '</td><td class="num">' . NumberHelper::toGermanFormat($total + $fee, 2, withThousandsSeparator: true) . ' €</td></tr>'
                . '</tfoot>' : '')
            . '</table>';
    }

    /** Stundenzettel: Zeitentabelle mit Tagessummen. */
    private function timesheetTable(DesignContext $design, string $scenario): string {
        $days = $scenario === self::SCENARIO_MANY_ROWS ? 30 : 10;
        $rows = '';
        $totalMinutes = 0;
        for ($d = 1; $d <= $days; $d++) {
            $minutes = 420 + ($d % 4) * 30;
            $totalMinutes += $minutes;
            $rows .= sprintf(
                '<tr><td>%02d.06.2026</td><td>08:00</td><td>%s</td><td class="num">0:30</td><td class="num">%d:%02d</td></tr>',
                $d,
                sprintf('%02d:%02d', intdiv(510 + ($d % 4) * 30, 60), (510 + ($d % 4) * 30) % 60),
                intdiv($minutes, 60),
                $minutes % 60,
            );
        }

        return '<table><thead><tr><th>' . __('Datum') . '</th><th>' . __('Beginn') . '</th><th>' . __('Ende') . '</th><th class="num">' . __('Pause') . '</th><th class="num">' . __('Arbeitszeit') . '</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . ($design->show(InformationBlock::Totals) ? '<tfoot><tr><td colspan="4" class="num">' . __('Summe') . '</td><td class="num">'
                . intdiv($totalMinutes, 60) . ':' . sprintf('%02d', $totalMinutes % 60) . '</td></tr></tfoot>' : '')
            . '</table>';
    }

    /** Formular: Feld/Wert-Liste. */
    private function formTable(string $long): string {
        $fields = [
            [__('Objekt'), __('Halle 3, Musterstraße 1')],
            [__('Auftraggeber'), __('Beispiel GmbH')],
            [__('Prüfergebnis'), __('Ohne Mängel')],
            [__('Bemerkungen'), $long],
        ];
        $rows = '';
        foreach ($fields as [$label, $value]) {
            $rows .= '<tr><td style="width: 30%"><strong>' . $label . '</strong></td><td>' . $value . '</td></tr>';
        }

        return '<table><tbody>' . $rows . '</tbody></table>';
    }

    /** Bericht/Protokoll/Fallakte: Fließtext-Abschnitte plus Tätigkeitstabelle. */
    private function evidenceBody(string $scenario, string $long): string {
        $sections = '';
        $sectionCount = $scenario === self::SCENARIO_LONG_TEXT ? 6 : 3;
        for ($s = 1; $s <= $sectionCount; $s++) {
            $sections .= '<h2 style="font-size: 13px; margin: 12px 0 4px;">' . __('Abschnitt :n', ['n' => $s]) . '</h2><p>' . $long . '</p>';
        }

        $rowCount = $scenario === self::SCENARIO_MANY_ROWS ? 45 : 8;
        $rows = '';
        for ($i = 1; $i <= $rowCount; $i++) {
            $rows .= sprintf(
                '<tr><td>%02d.06.2026</td><td>%s</td><td class="num">%d:%02d</td></tr>',
                $i % 28 + 1,
                __('Tätigkeit :n', ['n' => $i]),
                1 + $i % 3,
                ($i * 15) % 60,
            );
        }

        return $sections
            . '<table><thead><tr><th>' . __('Datum') . '</th><th>' . __('Tätigkeit') . '</th><th class="num">' . __('Dauer') . '</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>';
    }
}
