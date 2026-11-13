<?php
/*
 * Created on   : Thu Nov 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoicesRepairSellerIdsCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Console\Concerns\IteratesOrganizations;
use App\Enums\Invoicing\{IncomingInvoiceMatchKind, IncomingInvoiceRecognition};
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\EInvoice\IncomingEInvoiceService;
use Illuminate\Console\Command;

/**
 * Rechnungseingang (MVP-1696): liest Steuer- und Registernummer des Verkäufers
 * strukturierter Bestandseingänge aus dem Original neu. Vor php-erechnung-toolkit
 * 0.22 stand bei UBL ohne Steuernummer die Registernummer im Feld der
 * Steuernummer. Einmalig nach dem Update; jeder Eingang wird höchstens einmal
 * bearbeitet (Marke `seller_register_number` in der Zusammenfassung).
 */
class IncomingInvoicesRepairSellerIdsCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'incoming-invoices:repair-seller-ids
        {--dry-run : Nur zählen, nichts speichern}
        {--limit=500 : Höchstens so viele gelesene Eingänge je Organisation}
        ' . self::ORGANIZATION_OPTION;

    protected $description = 'Liest Steuer- und Registernummer des Verkäufers strukturierter Bestandseingänge neu aus dem Original.';

    public function handle(IncomingEInvoiceService $invoices): int {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(1, (int) $this->option('limit'));

        $failures = $this->forEachOrganization(function (Organization $org) use ($invoices, $dryRun, $limit): void {
            $checked = 0;
            $corrected = 0;
            $rematched = 0;
            $unreadable = 0;
            /** @var list<string> $review */
            $review = [];
            $candidates = IncomingEInvoice::query()
                ->where('recognition', IncomingInvoiceRecognition::Structured->value)
                ->lazyById(200)
                ->filter(static fn (IncomingEInvoice $incoming): bool => ! array_key_exists('seller_register_number', (array) $incoming->summary));

            foreach ($candidates as $incoming) {
                if ($checked >= $limit) {
                    break;
                }
                $result = $invoices->correctSellerIds($incoming, $dryRun);
                // Ohne lesbares Original bleibt der Eingang offen; er zählt nicht gegen die Grenze, sonst blockierte er spätere Läufe.
                if ($result === null) {
                    $unreadable++;
                    continue;
                }
                $checked++;
                if (! $result['changed']) {
                    continue;
                }
                $corrected++;
                $rematched += $result['rematched'] ? 1 : 0;
                // Bestehende Zuordnungen bleiben; eine über die falsche Steuernummer nennt der Lauf zur Prüfung.
                if (! $result['rematched'] && $incoming->match_kind === IncomingInvoiceMatchKind::TaxNumber) {
                    $review[] = (string) $incoming->sqid;
                }
            }
            if ($checked + $unreadable === 0) {
                return;
            }
            $verb = $dryRun ? 'zu berichtigen (Trockenlauf)' : 'berichtigt';
            $this->line("Organisation #{$org->id}: {$checked} geprüft, {$corrected} Steuernummern {$verb}, {$rematched} neu zugeordnet, {$unreadable} ohne lesbares Original.");
            if ($review !== []) {
                $this->warn('  Über die alte Steuernummer zugeordnet, bitte prüfen: ' . implode(', ', $review));
            }
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
