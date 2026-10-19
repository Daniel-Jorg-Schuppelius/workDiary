<?php
/*
 * Created on   : Mon Oct 19 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TemplateRecognizer.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Invoicing\Recognition;

use App\Enums\Invoicing\IncomingInvoiceRecognition;
use App\Models\Invoicing\{IncomingEInvoice, IncomingInvoiceTemplate};
use App\Models\Platform\{Organization, User};
use App\Services\Invoicing\Recognition\Contracts\WordBoxSource;
use Illuminate\Support\Facades\Storage;
use PDFToolkit\Entities\WordBox;

/**
 * Belegvorlage anwenden (Phase 151, L5): bestätigte und gezeichnete Zonen
 * schlagen die Heuristik, gelernte unbestätigte füllen nur Lücken; die
 * Diagnose nennt je Feld die Quelle `zone`.
 */
final class TemplateRecognizer {
    public function __construct(
        private readonly WordBoxSource $boxes,
        private readonly TemplateMatcher $matcher,
        private readonly ZoneExtractor $extractor,
    ) {}

    /**
     * In der Erkennung: Ergebnis der Heuristik um Zonenwerte ergänzen.
     *
     * @param  array<string, mixed>  $result
     */
    public function apply(array &$result, string $pdfPath, Organization $organization): void {
        $boxes = $this->boxes->boxesFor($pdfPath);
        if ($boxes === []) {
            return;
        }
        $match = $this->matcher->match((int) $organization->id, $boxes, is_string($result['seller_vat'] ?? null) ? $result['seller_vat'] : null);
        if ($match === null) {
            return;
        }
        $template = $match['template'];
        $values = $this->valuesFor($template, $boxes);
        $sources = (array) ($result['sources'] ?? []);
        foreach ($values as $field => ['value' => $value, 'authoritative' => $authoritative]) {
            $key = ZoneFields::FIELDS[$field]['result'];
            if ($authoritative || ($result[$key] ?? null) === null) {
                $result[$key] = $value;
                $sources[$key] = 'zone';
            }
        }
        $result['sources'] = $sources;
        $result['template'] = ['id' => (int) $template->id, 'name' => (string) $template->name, 'similarity' => round($match['similarity'], 2), 'fields' => array_keys($values)];
        $template->forceFill(['hits' => (int) $template->hits + 1, 'last_matched_at' => now()])->saveQuietly();
    }

    /**
     * Vorlage auf einen abgelegten Eingang anwenden (Editor, „auf offene anwenden“).
     *
     * @return list<string> geänderte Felder
     */
    public function applyToIncoming(IncomingEInvoice $incoming, IncomingInvoiceTemplate $template, ?User $actor): array {
        $path = self::pdfPathFor($incoming);
        if ($path === null) {
            return [];
        }
        $boxes = $this->boxes->boxesFor($path);
        $values = $this->valuesFor($template, $boxes);
        if ($values === []) {
            return [];
        }
        $summary = (array) $incoming->summary;
        $extraction = (array) ($summary['extraction'] ?? []);
        $sources = (array) ($extraction['sources'] ?? []);
        $changes = ['template_id' => $template->id];
        $applied = [];
        foreach ($values as $field => ['value' => $value]) {
            $column = ZoneFields::FIELDS[$field]['column'];
            $changes[$column] = $value;
            $sources[ZoneFields::FIELDS[$field]['result']] = 'zone';
            $applied[] = $field;
        }
        $extraction['sources'] = $sources;
        $extraction['template'] = ['id' => (int) $template->id, 'name' => (string) $template->name, 'fields' => $applied];
        $summary['extraction'] = $extraction;
        if ($incoming->recognition === IncomingInvoiceRecognition::None
            && ($changes['invoice_number'] ?? $incoming->invoice_number) !== null
            && ($changes['amount_gross'] ?? $incoming->amount_gross) !== null) {
            $changes['recognition'] = IncomingInvoiceRecognition::Extracted;
        }
        $incoming->forceFill([...$changes, 'summary' => $summary])->save();
        $incoming->audit('incoming_einvoice.template_applied', ['template_id' => $template->id, 'fields' => $applied, 'actor_user_id' => $actor?->id]);
        $template->forceFill(['hits' => (int) $template->hits + 1, 'last_matched_at' => now()])->saveQuietly();

        return $applied;
    }

    /**
     * @param  list<WordBox>  $boxes
     * @return array<string, array{value: string, authoritative: bool}>
     */
    public function valuesFor(IncomingInvoiceTemplate $template, array $boxes): array {
        $values = [];
        foreach ($template->zoneList() as $zone) {
            $field = (string) ($zone['field'] ?? '');
            if (! isset(ZoneFields::FIELDS[$field]) || isset($values[$field])) {
                continue;
            }
            $value = $this->extractor->extract(['parse' => ZoneFields::parseOf($field)] + $zone, $boxes);
            if ($value === null) {
                continue;
            }
            // Gezeichnet oder zweimal bestätigt: die Zone gilt; sonst nur Lückenfüller (E4).
            $authoritative = ($zone['source'] ?? IncomingInvoiceTemplate::SOURCE_MANUAL) === IncomingInvoiceTemplate::SOURCE_MANUAL || (bool) ($zone['confirmed'] ?? false);
            $values[$field] = ['value' => $value, 'authoritative' => $authoritative];
        }

        return $values;
    }

    /** Pfad des Originals, wenn es ein PDF ist. */
    public static function pdfPathFor(IncomingEInvoice $incoming): ?string {
        $version = $incoming->document?->currentVersion;
        if ($version === null || ! str_contains(mb_strtolower((string) $version->mime), 'pdf')) {
            return null;
        }
        $disk = Storage::disk((string) ($version->disk ?: 'local'));

        return $disk->exists((string) $version->path) ? $disk->path((string) $version->path) : null;
    }
}
