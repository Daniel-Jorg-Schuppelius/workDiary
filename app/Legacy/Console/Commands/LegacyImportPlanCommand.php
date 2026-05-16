<?php
/*
 * Created on   : Wed Apr 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LegacyImportPlanCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Legacy\Console\Commands;

use App\Legacy\Models\LegacyDiaryEntry;
use App\Legacy\Models\LegacyUser;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class LegacyImportPlanCommand extends Command
{
    protected $signature = 'legacy:import-plan';

    protected $description = 'Zeigt den Umfang der Legacy-Daten für eine spätere Migration an';

    public function handle(): int
    {
        if (! filled(config('database.connections.legacy.database'))) {
            $this->error('Legacy-DB ist nicht konfiguriert. Bitte LEGACY_DB_* in der .env setzen.');

            return self::FAILURE;
        }

        try {
            DB::connection('legacy')->getPdo();

            $this->table(
                ['Bereich', 'Anzahl'],
                [
                    ['Mitarbeiter', (string) LegacyUser::query()->where('id', '>', 3)->count()],
                    ['Alle Nutzer', (string) LegacyUser::query()->count()],
                    ['Tagebuch-Einträge', (string) LegacyDiaryEntry::query()->count()],
                    ['Offene Einträge', (string) LegacyDiaryEntry::query()->where('gelesen', 2)->count()],
                    ['Problem-Einträge', (string) LegacyDiaryEntry::query()->where('gelesen', 3)->count()],
                ]
            );

            $this->info('Die Legacy-Tabellen können direkt gelesen werden. Ein dediziertes Importskript kann nun gezielt aufgebaut werden.');

            return self::SUCCESS;
        } catch (QueryException $exception) {
            $this->error('Legacy-DB nicht erreichbar: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
