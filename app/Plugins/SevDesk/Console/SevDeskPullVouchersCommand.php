<?php
/*
 * Created on   : Thu Aug 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SevDeskPullVouchersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\SevDesk\Console;

use App\Models\Organization;
use App\Plugins\SevDesk\Services\SevDeskVoucherPullService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Beleg-Rückabruf aus sevDesk (Feature 122, MVP-611): spiegelt Belege, die
 * direkt in der Buchhaltung entstanden sind, in die Belegliste.
 */
class SevDeskPullVouchersCommand extends Command {
    protected $signature = 'sevdesk:pull-vouchers
        {--organization= : ID einer einzelnen Organisation, sonst alle}
        {--pages=2 : Anzahl abzurufender Seiten (je 50 Belege, jüngste zuerst)}';

    protected $description = 'Spiegelt sevDesk-Belege in die Belegliste (Feature 122).';

    public function handle(SevDeskVoucherPullService $pull): int {
        $orgOption = $this->option('organization');
        $pages = max(1, (int) $this->option('pages'));
        $totals = ['read' => 0, 'created' => 0, 'updated' => 0];
        $failed = 0;

        $organizations = Organization::query()
            ->when(is_numeric($orgOption), fn ($q) => $q->whereKey((int) $orgOption))
            ->get();

        foreach ($organizations as $organization) {
            try {
                $result = $pull->pull((int) $organization->id, $pages);
                foreach ($totals as $key => $value) {
                    $totals[$key] = $value + $result[$key];
                }
            } catch (Throwable) {
                $failed++;
            }
        }

        $this->info(sprintf(
            'sevDesk-Belege: %d gelesen, %d neu, %d aktualisiert, %d Fehler',
            $totals['read'],
            $totals['created'],
            $totals['updated'],
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
