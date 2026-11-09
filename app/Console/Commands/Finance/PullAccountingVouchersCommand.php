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

use App\Models\Platform\Organization;
use App\Plugins\Support\PluginTenantGate;
use App\Services\Billing\RetainerChannelResolver;
use App\Services\Finance\Accounting\Articles\ArticleMirror;
use App\Services\Finance\Accounting\Vouchers\{LineAwareVoucherPuller, VoucherMirror};
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
    /** Belege je Lauf und Anbieter, deren Positionen nachgeladen werden (Einzelabrufe). */
    private const int LINE_BATCH = 100;

    protected $signature = 'accounting:pull-vouchers
        {--plugin= : Nur dieses Buchhaltungssystem (Plugin-ID: sevdesk, easybill, orgamax, jtl_wawi, invoiceplane, invoiceninja, akaunting)}
        {--organization= : ID einer einzelnen Organisation, sonst alle}
        {--pages=2 : Anzahl abzurufender Seiten je Anbieter}';

    protected $description = 'Spiegelt Belege der angebundenen Buchhaltungssysteme in die Belegliste (Feature 122).';

    public function handle(VoucherPullerRegistry $registry, ArticleMirror $articles, VoucherMirror $mirror, RetainerChannelResolver $retainers): int {
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
            // Gesperrte Mandanten: kein Plugin-Lauf (Entscheidung 2026-10-05), der nächste Lauf nach dem Entsperren holt nach.
            if (PluginTenantGate::blocks((int) $organization->id)) {
                continue;
            }
            // Artikelkatalog vor den Belegen (gap-09): so tragen neue Positionen gleich den Katalogschlüssel.
            foreach ($articles->sources() as $source) {
                if (($pluginOption !== '' && $source->pluginId() !== $pluginOption) || ! $source->isConfigured((int) $organization->id)) {
                    continue;
                }
                try {
                    $result = $articles->sync((int) $organization->id, $source);
                    $this->line(sprintf('Org %d · %s: %d Artikel gelesen, %d neu, %d aktualisiert, %d archiviert, %d Positionen verknüpft', $organization->id, $source->pluginId(), $result['read'], $result['created'], $result['updated'], $result['archived'], $result['relinked']));
                } catch (Throwable $e) {
                    $this->error(sprintf('Org %d · %s: Artikel nicht vollständig gelesen (%s)', $organization->id, $source->pluginId(), class_basename($e)));
                    report($e);
                    $failed++;
                }
            }
            foreach ($registry->configuredFor((int) $organization->id) as $puller) {
                if ($pluginOption !== '' && $puller->pluginId() !== $pluginOption) {
                    continue;
                }

                try {
                    $result = $puller->pull((int) $organization->id, $pages);
                    foreach ($totals as $key => $value) {
                        $totals[$key] = $value + $result[$key];
                    }
                    $lines = $puller instanceof LineAwareVoucherPuller ? $mirror->pullPendingLines($puller, (int) $organization->id, self::LINE_BATCH) : null;
                    // Zahlstand der Pauschalen nach jedem Spiegellauf (Feature 098, MVP-1505).
                    $retainers->linksForPlugin($puller->pluginId())?->reconcile($organization);
                    $this->line(sprintf(
                        'Org %d · %s: %d gelesen, %d neu, %d aktualisiert%s',
                        $organization->id,
                        $puller->pluginId(),
                        $result['read'],
                        $result['created'],
                        $result['updated'],
                        $lines !== null ? sprintf(', Positionen für %d Belege', $lines) : '',
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
