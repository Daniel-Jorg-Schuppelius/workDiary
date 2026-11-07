<?php
/*
 * Created on   : Sat Nov 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoicesExtractDocumentsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Invoicing\IncomingInvoiceRecognition;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Illuminate\Console\Command;

/**
 * Rechnungseingang (Feature 196, MVP-1487): löst die eingebetteten Unterlagen
 * strukturierter Bestandseingänge nach, die vor der Einführung empfangen wurden.
 * Jeder Eingang wird höchstens einmal bearbeitet (Marke in der Zusammenfassung).
 */
class IncomingInvoicesExtractDocumentsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'incoming-invoices:extract-documents
        {--dry-run : Nur zählen, nichts ablegen}
        {--limit=500 : Höchstens so viele Eingänge je Organisation}
        ' . self::ORGANIZATION_OPTION;

    protected $description = 'Löst eingebettete Unterlagen (BG-24) aus strukturierten Bestandseingängen.';

    public function handle(IncomingEInvoiceService $invoices): int {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $failures = $this->forEachOrganization(function (Organization $org) use ($invoices, $dryRun, $limit): void {
            $candidates = IncomingEInvoice::query()
                ->where('recognition', IncomingInvoiceRecognition::Structured->value)
                ->orderBy('id')
                ->get()
                ->filter(static fn (IncomingEInvoice $incoming): bool => ! array_key_exists('supporting_documents', (array) $incoming->summary))
                ->take($limit);

            $found = 0;
            $missing = 0;
            foreach ($candidates as $incoming) {
                $original = $incoming->storedOriginal();
                if ($original === null) {
                    $missing++;
                    continue;
                }
                if ($dryRun) {
                    $xml = (string) $invoices->extractXml($original['contents'], $original['mime'], $original['path']);
                    $found += str_contains($xml, 'AdditionalDocumentReference') || str_contains($xml, 'AdditionalReferencedDocument') ? 1 : 0;
                    continue;
                }
                if ($invoices->storeSupportingDocuments($incoming, $original['contents'], $original['mime'], null)) {
                    $found++;
                }
            }
            if ($candidates->isEmpty()) {
                return;
            }
            $verb = $dryRun ? 'mit Verweisen (Trockenlauf)' : 'mit Unterlagen oder Hinweisen';
            $this->line("Organisation #{$org->id}: {$candidates->count()} geprüft, {$found} {$verb}, {$missing} ohne Original.");
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
