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

use App\Enums\DocumentDesign\{InformationBlock, RenderDocumentKind};
use App\Models\Organization;
use CommonToolkit\Helper\Data\NumberHelper;
use PDFToolkit\Entities\PDFContent;
use PDFToolkit\Registries\PDFWriterRegistry;
use RuntimeException;

/**
 * Testdokumente je Dokumentart (MVP-302): lange Texte, viele Positionen,
 * Rabatte, mehrere Steuersätze und erzwungener Seitenumbruch — direkt aus dem
 * Editor als PDF abrufbar, ohne echte Belegdaten zu benötigen.
 */
class SampleDocumentService {
    public function __construct(private readonly DocumentDesignRenderer $renderer) {}

    /** @param array<string, mixed>|null $payload Explizites Payload (z. B. Entwurf) statt aktives Profil. */
    public function pdf(Organization $organization, RenderDocumentKind $kind, ?array $payload = null): string {
        $payload ??= $this->renderer->payloadFor($organization, $kind);
        $html = $this->renderer->compose($this->sampleHtml($organization, $kind, $payload), $payload);

        return PDFWriterRegistry::getInstance()->createPdfString(PDFContent::fromHtml($html))
            ?? throw new RuntimeException('Test-PDF-Erzeugung fehlgeschlagen.');
    }

    /** @param array<string, mixed>|null $payload */
    public function sampleHtml(Organization $organization, RenderDocumentKind $kind, ?array $payload): string {
        $design = $this->renderer->context($payload);
        $long = str_repeat(__('Dieser Beispieltext prüft Zeilenumbruch, Silbentrennung und Blocksatzverhalten im gewählten Tabellenstil. '), 3);

        $rows = '';
        $net = 0.0;
        for ($i = 1; $i <= 32; $i++) {
            $qty = $i % 7 + 1;
            $price = round(11.9 + $i * 3.37, 2);
            $discount = $i % 5 === 0 ? 10 : 0;
            $sum = round($qty * $price * (1 - $discount / 100), 2);
            $net += $sum;
            $rows .= sprintf(
                '<tr><td>%d</td><td>%s%s</td><td class="num">%d</td><td class="num">%.2f €</td><td class="num">%s</td><td class="num">%.2f €</td></tr>',
                $i,
                __('Beispielposition :n', ['n' => $i]),
                $i % 9 === 0 ? '<br><span style="font-size: 90%">' . $long . '</span>' : '',
                $qty,
                $price,
                $discount > 0 ? $discount . ' %' : '—',
                $sum,
            );
        }
        $tax19 = round($net * 0.7 * 0.19, 2);
        $tax7 = round($net * 0.3 * 0.07, 2);

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
        $intro = $design->show(InformationBlock::IntroText) ? '<p>' . $long . '</p>' : '';
        $closing = $design->show(InformationBlock::ClosingText) ? '<p>' . $long . '</p>' : '';
        $confidential = $design->show(InformationBlock::Confidentiality)
            ? '<p style="font-size: 8px; color: #999;">' . __('Vertraulich — nur für den benannten Empfänger bestimmt.') . '</p>'
            : '';

        $meta = $design->show(InformationBlock::DocumentMeta)
            ? '<h1>' . $kind->label() . ' TEST-0001</h1><p>' . __('Datum') . ': 01.07.2026 · ' . __('Leistungszeitraum') . ': 01.06.–30.06.2026 · ' . __('Referenz') . ': PRJ-42</p>'
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
            . '<table><thead><tr><th>' . __('Pos.') . '</th><th>' . __('Bezeichnung') . '</th><th class="num">' . __('Menge') . '</th><th class="num">' . __('Einzelpreis') . '</th><th class="num">' . __('Rabatt') . '</th><th class="num">' . __('Summe') . '</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . ($design->show(InformationBlock::Totals) ? '<tfoot>'
                . '<tr><td colspan="5" class="num">' . __('Zwischensumme (netto)') . '</td><td class="num">' . NumberHelper::toGermanFormat($net, 2, withThousandsSeparator: true) . ' €</td></tr>'
                . ($design->show(InformationBlock::TaxBreakdown)
                    ? '<tr><td colspan="5" class="num">' . __('USt. 19 %') . '</td><td class="num">' . NumberHelper::toGermanFormat($tax19, 2, withThousandsSeparator: true) . ' €</td></tr>'
                    . '<tr><td colspan="5" class="num">' . __('USt. 7 %') . '</td><td class="num">' . NumberHelper::toGermanFormat($tax7, 2, withThousandsSeparator: true) . ' €</td></tr>'
                    : '')
                . '<tr><td colspan="5" class="num">' . __('Gesamtbetrag') . '</td><td class="num">' . NumberHelper::toGermanFormat($net + $tax19 + $tax7, 2, withThousandsSeparator: true) . ' €</td></tr>'
                . '</tfoot>' : '')
            . '</table>'
            . $closing
            . $identity . $tax . $bank . $confidential
            . '</div></body></html>';
    }
}
