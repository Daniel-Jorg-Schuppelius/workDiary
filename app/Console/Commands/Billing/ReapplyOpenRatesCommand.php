<?php
/*
 * Created on   : Sun Aug 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ReapplyOpenRatesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands\Billing;

use App\Console\Concerns\IteratesOrganizations;
use App\Models\{Customer, Organization, TimeEntry};
use App\Support\Sqid;
use CommonToolkit\Helper\Data\StringHelper;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Bewertet offene Zeiten ohne eigenen Satz-Snapshot neu (MVP-482) — gedacht
 * für die Einführung des Org-Standardsatzes und nach dessen Änderung: die
 * Einträge stehen sonst weiter mit ihrem alten `rate` in „Offene Zeiten".
 *
 * Grenzen bewusst eng: nur `exported = false` (abgerechnete Zeiten hängen an
 * Belegen), nur `hourly_rate IS NULL` (ein gepflegter Satz-Snapshot bleibt
 * unangetastet) und ohne saldo-geführte Kunden (dort schließt der
 * Monatsabschluss ab). Ohne --apply nur Dry-Run.
 */
class ReapplyOpenRatesCommand extends Command {
    use IteratesOrganizations;

    protected $signature = 'billing:reapply-open-rates '
        . self::ORGANIZATION_OPTION
        . ' {--customer= : ID oder Sqid eines einzelnen Kunden}'
        . ' {--from= : Leistungsdatum ab (YYYY-MM-DD)}'
        . ' {--to= : Leistungsdatum bis (YYYY-MM-DD)}'
        . ' {--apply : Änderungen schreiben (sonst Dry-Run)}';

    protected $description = 'Bewertet offene Zeiteinträge ohne eigenen Stundensatz neu (Org-Standardsatz). Ohne --apply nur Dry-Run.';

    public function handle(): int {
        $apply = (bool) $this->option('apply');

        $failures = $this->forEachOrganization(function (Organization $org) use ($apply): void {
            $customerId = $this->customerId();
            if ($this->option('customer') !== null && $this->option('customer') !== '' && $customerId === null) {
                $this->warn(sprintf('Organisation #%d (%s): Kunde nicht gefunden — übersprungen.', $org->id, $org->name));

                return;
            }

            $touched = 0;
            $before = 0.0;
            $after = 0.0;
            /** @var Collection<int, string> $samples */
            $samples = collect();

            $this->query($customerId)->chunkById(200, function (Collection $entries) use ($apply, &$touched, &$before, &$after, $samples): void {
                foreach ($entries as $entry) {
                    $old = $entry->rate?->toFloat() ?? 0.0;
                    // Direkt rechnen: bei einem Eintrag ohne dirty-Feld liefe der
                    // saving-Hook nicht und der Satz bliebe stehen.
                    $entry->applyRateSnapshot();
                    $new = $entry->rate?->toFloat() ?? 0.0;

                    if (abs($new - $old) < 0.005) {
                        continue;
                    }

                    $touched++;
                    $before += $old;
                    $after += $new;
                    if ($samples->count() < 5) {
                        $samples->push(sprintf(
                            '  #%d %s %s: %s € → %s €',
                            $entry->id,
                            $entry->date?->format('d.m.Y') ?? '—',
                            StringHelper::truncate((string) $entry->description, 40),
                            number_format($old, 2, ',', '.'),
                            number_format($new, 2, ',', '.'),
                        ));
                    }

                    if ($apply) {
                        $entry->save();
                    }
                }
            });

            foreach ($samples as $line) {
                $this->line($line);
            }

            $mode = $apply ? 'neu bewertet' : 'würden neu bewertet (Dry-Run, --apply zum Schreiben)';
            $this->info(sprintf(
                'Organisation #%d (%s): %d Einträge %s — %s € → %s €.',
                $org->id,
                $org->name,
                $touched,
                $mode,
                number_format($before, 2, ',', '.'),
                number_format($after, 2, ',', '.'),
            ));
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return Builder<TimeEntry> */
    private function query(?int $customerId): Builder {
        $from = (string) ($this->option('from') ?? '');
        $to = (string) ($this->option('to') ?? '');

        return TimeEntry::query()
            ->withoutLedgerManagedCustomers()
            ->where('exported', false)
            ->whereNull('hourly_rate')
            ->when($customerId !== null, fn(Builder $q) => $q->whereHas('project', fn(Builder $p) => $p->where('customer_id', $customerId)))
            ->when($from !== '', fn(Builder $q) => $q->whereDate('date', '>=', $from))
            ->when($to !== '', fn(Builder $q) => $q->whereDate('date', '<=', $to));
    }

    /** Kunden-Option als ID oder Sqid; null = alle Kunden der Organisation. */
    private function customerId(): ?int {
        $value = (string) ($this->option('customer') ?? '');
        if ($value === '') {
            return null;
        }

        $id = Sqid::decodeOrNumeric(Customer::class, $value);

        return $id !== null && Customer::query()->whereKey($id)->exists() ? $id : null;
    }
}
