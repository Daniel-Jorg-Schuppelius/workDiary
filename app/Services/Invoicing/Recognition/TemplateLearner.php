<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TemplateLearner.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Models\Invoicing\{IncomingEInvoice, IncomingInvoiceTemplate};
use App\Models\Platform\User;
use App\Services\Invoicing\Recognition\Contracts\WordBoxSource;
use CommonToolkit\Enums\CountryCode;
use CommonToolkit\Helper\Data\{DateHelper, NumberHelper, StringHelper, VatNumberHelper};
use PDFToolkit\Entities\WordBox;

/**
 * Lernen aus Korrekturen (Phase 151, L4): jeder im Dialog „Werte erfassen“
 * eingegebene Wert wird im Wortgitter des Belegs gesucht; ein eindeutiger
 * Treffer wird zur Zone der Vorlage des Lieferanten. Eine gelernte Zone gilt
 * erst nach dem zweiten übereinstimmenden Treffer als bestätigt (E4);
 * gezeichnete Zonen bleiben unangetastet.
 */
final class TemplateLearner {
    private const MARGIN_X = 0.01;

    private const MARGIN_Y = 0.004;

    public function __construct(private readonly WordBoxSource $boxes, private readonly TemplateMatcher $matcher) {}

    /**
     * @param  array<string, mixed>  $values  Eingaben des Dialogs (invoice_number, issue_date, …, party_vat_id)
     * @return array{template: IncomingInvoiceTemplate|null, fields: list<string>}
     */
    public function learnFromValues(IncomingEInvoice $incoming, array $values, User $actor): array {
        $path = TemplateRecognizer::pdfPathFor($incoming);
        if ($path === null) {
            return ['template' => null, 'fields' => []];
        }
        $boxes = $this->boxes->boxesFor($path);
        if ($boxes === []) {
            return ['template' => null, 'fields' => []];
        }
        $wanted = [
            'invoice_number' => $values['invoice_number'] ?? null,
            'issue_date' => $values['issue_date'] ?? null,
            'due_date' => $values['due_date'] ?? null,
            'amount_net' => $values['amount_net'] ?? null,
            'amount_tax' => $values['amount_tax'] ?? null,
            'amount_gross' => $values['amount_gross'] ?? null,
            'seller_vat_id' => $values['party_vat_id'] ?? null,
        ];
        $zones = [];
        foreach ($wanted as $field => $value) {
            if (! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }
            $box = $this->locate($field, (string) $value, $boxes);
            if ($box !== null) {
                $zones[$field] = $this->zoneAround($box, $field);
            }
        }
        if ($zones === []) {
            return ['template' => null, 'fields' => []];
        }
        $template = $this->templateFor($incoming, $boxes, $actor);
        $merged = $this->mergeZones($template->zoneList(), $zones);
        $template->forceFill(['zones' => $merged])->save();
        if ($incoming->template_id === null) {
            $incoming->forceFill(['template_id' => $template->id])->saveQuietly();
        }
        $incoming->audit('incoming_einvoice.template_learned', ['template_id' => $template->id, 'fields' => array_keys($zones)]);

        return ['template' => $template, 'fields' => array_keys($zones)];
    }

    /**
     * @param  list<WordBox>  $boxes
     */
    private function locate(string $field, string $value, array $boxes): ?WordBox {
        $parse = ZoneFields::parseOf($field);
        $target = self::normalize($value, $parse);
        if ($target === null) {
            return null;
        }
        $hits = [];
        foreach ($boxes as $box) {
            if (self::normalize($box->text, $parse) === $target) {
                $hits[] = $box;
            }
        }
        // Nur ein eindeutiger Treffer wird zur Zone — zwei gleiche Werte (Netto = Brutto bei 0 % Steuer) bleiben offen.
        return count($hits) === 1 ? $hits[0] : null;
    }

    private static function normalize(string $text, string $parse): ?string {
        $text = trim($text, " \t:;,");
        if ($text === '') {
            return null;
        }

        return match ($parse) {
            'date' => preg_match('/\d{1,2}[.\/-]\d{1,2}[.\/-]\d{2,4}|\d{4}-\d{2}-\d{2}/', $text, $m) === 1 ? DateHelper::parseWithCountryPreference($m[0], CountryCode::Germany)?->format('Y-m-d') : null,
            'money' => ($n = NumberHelper::normalizeDecimalStringOrNull(preg_replace('/[^\d.,+-]/u', '', $text) ?? $text, CountryCode::Germany)) !== null ? NumberHelper::toUSFormat((float) $n, 2) : null,
            'vat_id' => ($vat = VatNumberHelper::normalize($text)) !== '' ? $vat : null,
            default => mb_strtoupper(StringHelper::removeWhitespace($text, unicode: true)),
        };
    }

    /** @return array<string, mixed> */
    private function zoneAround(WordBox $box, string $field): array {
        $clamp = static fn (float $v): float => max(0.0, min(1.0, $v));

        return [
            'field' => $field,
            'page' => $box->page,
            'x' => $clamp($box->x - self::MARGIN_X),
            'y' => $clamp($box->y - self::MARGIN_Y),
            'w' => $clamp($box->w + 2 * self::MARGIN_X),
            'h' => $clamp($box->h + 2 * self::MARGIN_Y),
            'parse' => ZoneFields::parseOf($field),
            'source' => IncomingInvoiceTemplate::SOURCE_LEARNED,
            'confirmed' => false,
        ];
    }

    /** @param  list<WordBox>  $boxes */
    private function templateFor(IncomingEInvoice $incoming, array $boxes, User $actor): IncomingInvoiceTemplate {
        if ($incoming->template_id !== null && ($template = $incoming->template) !== null) {
            return $template;
        }
        if ($incoming->supplier_id !== null) {
            $bySupplier = IncomingInvoiceTemplate::query()->withoutGlobalScopes()
                ->where('organization_id', $incoming->organization_id)->where('supplier_id', $incoming->supplier_id)->orderBy('id')->first();
            if ($bySupplier !== null) {
                return $bySupplier;
            }
        }
        $match = $this->matcher->match((int) $incoming->organization_id, $boxes, $incoming->seller_vat_id, $incoming->supplier_id);
        if ($match !== null) {
            return $match['template'];
        }
        $name = trim((string) ($incoming->counterpartyName() ?? $incoming->counterparty()?->name));
        $template = IncomingInvoiceTemplate::query()->create([
            'organization_id' => $incoming->organization_id,
            'supplier_id' => $incoming->supplier_id,
            'name' => mb_substr($name !== '' ? $name : (string) __('Gelernt am :date', ['date' => now()->toDateString()]), 0, 191),
            'fingerprint' => TemplateFingerprint::fromBoxes($boxes),
            'zones' => [],
            'source' => IncomingInvoiceTemplate::SOURCE_LEARNED,
            'created_by' => $actor->id,
        ]);
        $template->audit('incoming_einvoice.template_saved', ['source' => IncomingInvoiceTemplate::SOURCE_LEARNED]);

        return $template;
    }

    /**
     * Zweiter übereinstimmender Treffer bestätigt eine gelernte Zone; ein
     * abweichender ersetzt sie unbestätigt; gezeichnete Zonen bleiben.
     *
     * @param  list<array<string, mixed>>  $existing
     * @param  array<string, array<string, mixed>>  $learned
     * @return list<array<string, mixed>>
     */
    private function mergeZones(array $existing, array $learned): array {
        $result = [];
        $seen = [];
        foreach ($existing as $zone) {
            $field = (string) ($zone['field'] ?? '');
            if (! isset($learned[$field])) {
                $result[] = $zone;

                continue;
            }
            $seen[$field] = true;
            if (($zone['source'] ?? '') === IncomingInvoiceTemplate::SOURCE_MANUAL) {
                $result[] = $zone;
            } elseif (self::overlaps($zone, $learned[$field])) {
                $result[] = ['confirmed' => true] + $zone;
                $result[count($result) - 1]['confirmed'] = true;
            } else {
                $result[] = $learned[$field];
            }
        }
        foreach ($learned as $field => $zone) {
            if (! isset($seen[$field])) {
                $result[] = $zone;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function overlaps(array $a, array $b): bool {
        if ((int) ($a['page'] ?? 1) !== (int) ($b['page'] ?? 1)) {
            return false;
        }
        $ax1 = (float) $a['x'] + (float) $a['w'];
        $ay1 = (float) $a['y'] + (float) $a['h'];
        $bx1 = (float) $b['x'] + (float) $b['w'];
        $by1 = (float) $b['y'] + (float) $b['h'];
        $iw = max(0.0, min($ax1, $bx1) - max((float) $a['x'], (float) $b['x']));
        $ih = max(0.0, min($ay1, $by1) - max((float) $a['y'], (float) $b['y']));
        $inter = $iw * $ih;
        $union = (float) $a['w'] * (float) $a['h'] + (float) $b['w'] * (float) $b['h'] - $inter;

        return $union > 0 && $inter / $union >= 0.4;
    }
}
