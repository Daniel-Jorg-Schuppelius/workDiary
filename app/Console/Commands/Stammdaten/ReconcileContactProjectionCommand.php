<?php
/*
 * Created on   : Tue Nov 17 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReconcileContactProjectionCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Stammdaten;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\Customer\Customer;
use App\Models\Platform\Organization;
use App\Models\Supplier\Supplier;
use App\Services\Stammdaten\ContactDetailsWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gleicht Kunden und Lieferanten an, deren Projektion (`address_*`, `country`,
 * `bank_*`) vom primären Satelliten abweicht (MVP-1725); die Regel steht in
 * {@see ContactDetailsWriter::reconcileProjection()}. Ausgegeben werden nur
 * Art, ID und Spaltennamen, keine Werte.
 */
class ReconcileContactProjectionCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'contacts:reconcile-projection ' . self::ORGANIZATION_OPTION . '
        {--dry-run : Nur anzeigen, nichts schreiben}';

    protected $description = 'Gleicht Anschrift und Bankverbindung von Kunden und Lieferanten an, deren Projektion vom Satelliten abweicht.';

    public function handle(ContactDetailsWriter $writer): int {
        $apply = ! (bool) $this->option('dry-run');
        $total = 0;

        $failures = $this->forEachOrganization(function (Organization $organization) use ($writer, $apply, &$total): void {
            foreach ([Customer::class, Supplier::class] as $class) {
                $class::query()->where('organization_id', $organization->id)->orderBy('id')
                    ->chunkById(200, function ($parties) use ($writer, $apply, $organization, &$total): void {
                        foreach ($parties as $party) {
                            $drifts = DB::transaction(function () use ($writer, $party, $apply): array {
                                $locked = $party->newQuery()->whereKey($party->getKey())->lockForUpdate()->first();

                                return $locked instanceof Customer || $locked instanceof Supplier ? $writer->reconcileProjection($locked, $apply) : [];
                            });
                            foreach ($drifts as $drift) {
                                $total++;
                                $this->line(sprintf(
                                    'Organisation #%d · %s #%d · %s: %s → %s',
                                    $organization->id,
                                    class_basename($party),
                                    $party->getKey(),
                                    $drift['group'] === 'address' ? 'Anschrift' : 'Bankverbindung',
                                    implode(', ', $drift['columns']),
                                    match ($drift['resolution']) {
                                        'adopted' => 'Satellit aus der Projektion angelegt',
                                        'satellite' => 'Satellit gilt (jünger)',
                                        default => 'Projektion gilt, leere Felder aus dem Satelliten',
                                    },
                                ));
                            }
                        }
                    });
            }
        });

        $this->info(sprintf('%d Abweichung(en) %s.', $total, $apply ? 'angeglichen' : 'gefunden (Probelauf, nichts geschrieben)'));

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
