<?php
/*
 * Created on   : Wed Aug 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : PullAccountingVouchersCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Finance;

use App\Models\Organization;
use App\Services\Finance\Accounting\Vouchers\VoucherPullerRegistry;
use Illuminate\Console\Command;
use Throwable;

/**
 * Beleg-Rückabruf aus den angebundenen Buchhaltungssystemen (Feature 122,
 * MVP-731 — Vollscan G18).
 *
 * Ein Kommando für alle Anbieter statt vier: die Dublettenregel, die
 * Kontaktzuordnung und der Inkrement-Marker sind ohnehin gemeinsam
 * ({@see \App\Services\Finance\Accounting\Vouchers\VoucherMirror}). Ohne
 * `--plugin` laufen alle für die Organisation eingerichteten Puller; nicht
 * eingerichtete werden übersprungen, das ist keine Störung.
 */
class PullAccountingVouchersCommand extends Command {
    protected $signature = 'accounting:pull-vouchers
        {--plugin= : Nur dieses Buchhaltungssystem (sevdesk, easybill, invoiceplane, jtl_wawi)}
        {--organization= : ID einer einzelnen Organisation, sonst alle}
        {--pages=2 : Anzahl abzurufender Seiten je Anbieter}';

    protected $description = 'Spiegelt Belege der angebundenen Buchhaltungssysteme in die Belegliste (Feature 122).';

    public function handle(VoucherPullerRegistry $registry): int {
        $pluginOption = trim((string) $this->option('plugin'));
        $orgOption = $this->option('organization');
        $pages = max(1, (int) $this->option('pages'));

        if ($pluginOption !== '' && $registry->find($pluginOption) === null) {
            $this->error(sprintf(
                'Unbekanntes Buchhaltungssystem "%s". Bekannt: %s',
                $pluginOption,
                implode(', ', $registry->pluginIds()),
            ));

            return self::FAILURE;
        }

        $totals = ['read' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        $failed = 0;

        $organizations = Organization::query()
            ->when(is_numeric($orgOption), fn ($q) => $q->whereKey((int) $orgOption))
            ->get();

        foreach ($organizations as $organization) {
            foreach ($registry->configuredFor((int) $organization->id) as $puller) {
                if ($pluginOption !== '' && $puller->pluginId() !== $pluginOption) {
                    continue;
                }

                try {
                    $result = $puller->pull((int) $organization->id, $pages);
                    foreach ($totals as $key => $value) {
                        $totals[$key] = $value + $result[$key];
                    }
                    $this->line(sprintf(
                        'Org %d · %s: %d gelesen, %d neu, %d aktualisiert',
                        $organization->id,
                        $puller->pluginId(),
                        $result['read'],
                        $result['created'],
                        $result['updated'],
                    ));
                } catch (Throwable $e) {
                    // Ein Anbieter darf die anderen nicht mitreißen.
                    $this->error(sprintf('Org %d · %s: %s', $organization->id, $puller->pluginId(), class_basename($e)));
                    report($e);
                    $failed++;
                }
            }
        }

        $this->info(sprintf(
            'Belegabruf: %d gelesen, %d neu, %d aktualisiert, %d übersprungen, %d Fehler',
            $totals['read'],
            $totals['created'],
            $totals['updated'],
            $totals['skipped'],
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
