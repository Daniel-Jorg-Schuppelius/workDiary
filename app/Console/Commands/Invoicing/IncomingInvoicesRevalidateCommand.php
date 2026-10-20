<?php
/*
 * Created on   : Tue Oct 20 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoicesRevalidateCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Invoicing\{IncomingEInvoiceStatus, IncomingInvoiceRecognition};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Illuminate\Console\Command;

/**
 * Rechnungseingang (MVP-1248): wiederholt die Eingangs-Validierung (XSD +
 * KoSIT) strukturierter E-Rechnungen am abgelegten Original — standardmäßig
 * nur für Eingänge ohne KoSIT-Urteil, etwa weil der Validator beim Empfang
 * fehlte. Werte, Zuordnung, Regeln und Übergaben bleiben unangetastet; jeder
 * KoSIT-Lauf kostet einen JVM-Start von einigen Sekunden.
 */
class IncomingInvoicesRevalidateCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'incoming-invoices:revalidate {--all : Auch Eingänge mit vorhandenem KoSIT-Urteil erneut prüfen} ' . self::ORGANIZATION_OPTION;

    protected $description = 'Wiederholt die Schema- und KoSIT-Prüfung strukturierter E-Rechnungen im Rechnungseingang.';

    public function handle(IncomingEInvoiceService $invoices): int {
        $all = (bool) $this->option('all');
        $failures = $this->forEachOrganization(function (Organization $org) use ($invoices, $all): void {
            $checked = 0;
            $skipped = 0;
            $unreadable = 0;
            $cases = IncomingEInvoice::query()
                ->where('recognition', IncomingInvoiceRecognition::Structured->value)
                ->where('status', '!=', IncomingEInvoiceStatus::Rejected->value)
                ->orderBy('id')
                ->get();
            foreach ($cases as $incoming) {
                if (! $all && ! IncomingEInvoiceService::lacksKositVerdict($incoming)) {
                    $skipped++;
                } elseif ($invoices->revalidate($incoming)) {
                    $checked++;
                } else {
                    $unreadable++;
                }
            }
            if ($cases->isEmpty()) {
                return;
            }
            $this->line("Organisation #{$org->id}: {$checked} neu geprüft, {$skipped} mit vorhandenem Urteil übersprungen, {$unreadable} ohne lesbares Original.");
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
