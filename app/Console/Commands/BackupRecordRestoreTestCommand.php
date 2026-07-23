<?php
/*
 * Created on   : Thu Jul 23 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : BackupRecordRestoreTestCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Console\Commands;

use App\Enums\Backup\RestoreTestResult;
use App\Models\RestoreTest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Trägt einen durchgeführten Restore-Test ins Register ein (MVP-046 §6) —
 * CLI-Gegenstück zur UI-Erfassung, gedacht für scripts/restore-test.sh.
 * `performed_by_user_id` bleibt leer (Skript-Lauf); das Audit-Log entsteht
 * über das Auditable-Trait des Modells.
 */
class BackupRecordRestoreTestCommand extends Command {
    protected $signature = 'workdiary:backup:record-restore-test
        {--source=script-backup : Quelle des getesteten Backups (max. 191 Zeichen)}
        {--result=passed : Ergebnis: passed, partial oder failed}
        {--tested-on= : Testdatum (Default: heute; nicht in der Zukunft)}
        {--scope= : Umfang, z. B. "DB+Storage+.env, Stand 20260723_2300"}
        {--restored-size-bytes= : Wiederhergestellte Bytes}
        {--duration-minutes= : Dauer in Minuten (RTO)}
        {--notes= : Freitext (max. 5000 Zeichen)}';

    protected $description = 'Trägt einen durchgeführten Restore-Test ins Register ein (für Skripte/Automation).';

    public function handle(): int {
        $result = RestoreTestResult::tryFrom((string) $this->option('result'));
        if ($result === null) {
            $this->error('--result muss passed, partial oder failed sein.');

            return self::INVALID;
        }

        try {
            $testedOn = $this->option('tested-on') !== null
                ? CarbonImmutable::parse((string) $this->option('tested-on'))
                : CarbonImmutable::today();
        } catch (Throwable) {
            $this->error('--tested-on ist kein gültiges Datum.');

            return self::INVALID;
        }
        if ($testedOn->isFuture()) {
            $this->error('--tested-on darf nicht in der Zukunft liegen.');

            return self::INVALID;
        }

        $source = trim((string) $this->option('source'));
        if ($source === '' || mb_strlen($source) > 191) {
            $this->error('--source muss angegeben sein (max. 191 Zeichen).');

            return self::INVALID;
        }

        $size = $this->option('restored-size-bytes');
        $duration = $this->option('duration-minutes');
        if (($size !== null && (int) $size < 0) || ($duration !== null && (int) $duration < 0)) {
            $this->error('--restored-size-bytes/--duration-minutes dürfen nicht negativ sein.');

            return self::INVALID;
        }

        $test = RestoreTest::create([
            'source' => $source,
            'tested_on' => $testedOn,
            'result' => $result,
            'scope' => $this->option('scope') !== null ? mb_substr((string) $this->option('scope'), 0, 191) : null,
            'restored_size_bytes' => $size !== null ? (int) $size : null,
            'duration_minutes' => $duration !== null ? (int) $duration : null,
            'notes' => $this->option('notes') !== null ? mb_substr((string) $this->option('notes'), 0, 5000) : null,
            'performed_by_user_id' => null,
        ]);

        $this->info(sprintf('Restore-Test erfasst (#%d): %s am %s — %s.',
            $test->id, $source, $testedOn->format('d.m.Y'), $result->value));

        return self::SUCCESS;
    }
}
