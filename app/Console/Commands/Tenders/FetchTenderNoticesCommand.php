<?php
/*
 * Created on   : Mon Aug 18 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FetchTenderNoticesCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Tenders;

use App\Models\Tenders\TenderNotice;
use App\Services\Tenders\{TenderNoticeImporter, TenderNoticeMatcher};
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Täglicher Abruf des Bekanntmachungsservice (MVP-629/630).
 *
 * Läuft nach Mitternacht für den **Vortag**: Die Quelle stellt einen Tag erst
 * dann vollständig bereit. Der Abruf ist wiederholbar — bereits bekannte
 * Fassungen werden übersprungen, nicht doppelt angelegt.
 */
final class FetchTenderNoticesCommand extends Command {
    protected $signature = 'tenders:fetch-notices
                            {--day= : Veröffentlichungstag (YYYY-MM-DD), Vorgabe: gestern}
                            {--no-match : Nur abrufen, nicht gegen die Suchprofile abgleichen}';

    protected $description = 'Holt die Bekanntmachungen des Bundes (OpenData, CC0) und gleicht sie gegen die Suchprofile ab.';

    public function handle(TenderNoticeImporter $importer, TenderNoticeMatcher $matcher): int {
        $day = $this->option('day') !== null ? Carbon::parse((string) $this->option('day')) : null;

        $result = $importer->importDay($day);
        $this->info(sprintf(
            '%s: %d Bekanntmachungen gelesen, %d neu.',
            $result['day'],
            $result['fetched'],
            $result['stored'],
        ));

        if ($this->option('no-match') || $result['stored'] === 0) {
            return self::SUCCESS;
        }

        // Genau die neuen Fassungen abgleichen - ältere hatten ihre Chance,
        // und das Veröffentlichungsdatum ist nicht der Abruftag.
        $matches = $matcher->match(
            TenderNotice::query()->whereIn('id', $result['ids'])->cursor()
        );
        $this->info(sprintf('%d neue Treffer in den Suchprofilen.', $matches));

        return self::SUCCESS;
    }
}
