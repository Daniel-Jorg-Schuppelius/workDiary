<?php
/*
 * Created on   : Thu Nov 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IncomingInvoicesRefreshMailCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Invoicing;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Invoicing\IncomingEInvoice;
use App\Models\Platform\Organization;
use App\Services\Invoicing\Mail\IncomingMailOriginRefresher;
use Illuminate\Console\Command;

/**
 * Rechnungseingang (MVP-1813): lädt Text, Empfangszeit und Postfach der
 * Herkunfts-Mail für Eingänge nach, die vor MVP-1811/1812 aus dem Postfach
 * kamen. Je Eingang eine Suche nach der Message-ID im Postfach; Mails, die das
 * Postfach nicht mehr hat, bleiben ohne Text.
 */
class IncomingInvoicesRefreshMailCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'incoming-invoices:refresh-mail {--open : Nur offene, nicht übergebene Eingänge} {--limit=0 : Höchstzahl je Organisation (0 = alle)} ' . self::ORGANIZATION_OPTION;

    protected $description = 'Lädt den Text der Herkunfts-Mail für Eingänge des Rechnungseingangs aus dem Postfach nach.';

    public function handle(IncomingMailOriginRefresher $refresher): int {
        $limit = max(0, (int) $this->option('limit'));
        $failures = $this->forEachOrganization(function (Organization $org) use ($refresher, $limit): void {
            /** @var array<string, int> $counts */
            $counts = [];
            $query = IncomingEInvoice::query()->whereNull('mail_body')->whereNotNull('source_reference')->whereNotNull('sender_email')
                ->when((bool) $this->option('open'), static fn ($open) => $open->openForWork()->whereNull('transferred_at'))
                ->orderByDesc('id')
                ->when($limit > 0, static fn ($limited) => $limited->limit($limit));
            foreach ($query->get() as $incoming) {
                $outcome = $refresher->refresh($incoming);
                $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            }
            if ($counts !== []) {
                $this->line(sprintf(
                    'Organisation #%d: %d nachgeladen, %d nicht mehr im Postfach, %d Postfach nicht erreichbar, %d ohne Postfach.',
                    $org->id,
                    $counts[IncomingMailOriginRefresher::REFRESHED] ?? 0,
                    $counts[IncomingMailOriginRefresher::NOT_FOUND] ?? 0,
                    $counts[IncomingMailOriginRefresher::FAILED] ?? 0,
                    $counts[IncomingMailOriginRefresher::NOT_APPLICABLE] ?? 0,
                ));
            }
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
