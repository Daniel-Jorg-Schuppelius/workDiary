<?php
/*
 * Created on   : Sun Oct 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoicesReanalyzeCommand.php
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
 * Rechnungseingang (MVP-1207): wiederholt die Erkennung offener Klärfälle —
 * etwa nachdem pdftotext, tesseract und ocrmypdf auf dem Server nachinstalliert
 * wurden. Erfasste Werte, Übergaben und Ablehnungen bleiben unangetastet.
 */
class IncomingInvoicesReanalyzeCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'incoming-invoices:reanalyze ' . self::ORGANIZATION_OPTION;

    protected $description = 'Wiederholt die Erkennung offener Klärfälle im Rechnungseingang.';

    public function handle(IncomingEInvoiceService $invoices): int {
        $failures = $this->forEachOrganization(function (Organization $org) use ($invoices): void {
            $recognized = 0;
            $unchanged = 0;
            $skipped = 0;
            $cases = IncomingEInvoice::query()
                ->where('recognition', IncomingInvoiceRecognition::None->value)
                ->orderBy('id')
                ->get();
            foreach ($cases as $incoming) {
                if (! IncomingEInvoiceService::isReanalyzable($incoming)) {
                    $skipped++;
                } elseif ($invoices->reanalyze($incoming)) {
                    $recognized++;
                } else {
                    $unchanged++;
                }
            }
            if ($cases->isEmpty()) {
                return;
            }
            $this->line("Organisation #{$org->id}: {$recognized} erkannt, {$unchanged} weiterhin Klärfall, {$skipped} übersprungen (Werte erfasst, übergeben oder abgelehnt).");
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
