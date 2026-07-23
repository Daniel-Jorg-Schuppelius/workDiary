<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FreezeCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Integrity;

use App\Models\User;
use App\Services\Release\CodeIntegrityService;
use Illuminate\Console\Command;

/**
 * Friert die lokale Quelltext-Baseline ein (Feature 095, MVP-439):
 * integrity.json mit source=local — unsigniert, erkennt Drift ab diesem
 * Zeitpunkt. Erzeugung wird als baseline-Zeile + audit_logs-Ketteneintrag
 * verankert (ein Re-Freeze hinterlässt immer eine Spur).
 */
class FreezeCommand extends Command {
    protected $signature = 'integrity:freeze
        {--user= : E-Mail des auslösenden Nutzers (Provenienz, optional)}
        {--yes : Bestehende Baseline ohne Rückfrage überschreiben}';

    protected $description = 'Friert die lokale Quelltext-Baseline ein (integrity.json, source=local).';

    public function handle(CodeIntegrityService $service): int {
        $creator = null;
        $email = $this->option('user');
        if (is_string($email) && $email !== '') {
            $creator = User::query()->where('email', $email)->first();
            if ($creator === null) {
                $this->error('Kein Nutzer mit dieser E-Mail gefunden: ' . $email);

                return self::FAILURE;
            }
        }

        if ($service->load() !== null && ! (bool) $this->option('yes')) {
            if (! $this->confirm('Es existiert bereits eine Baseline — überschreiben?', false)) {
                $this->info('Abgebrochen — Baseline unverändert.');

                return self::SUCCESS;
            }
        }

        $check = $service->freeze('local', $creator);

        $this->info(sprintf(
            'Lokale Baseline eingefroren: %d Dateien, Root %s',
            $check->files_checked,
            (string) $check->baseline_root,
        ));
        $this->line('Hinweis: unsignierte lokale Baseline — erkennt Drift ab jetzt, belegt keine Herkunft.');

        return self::SUCCESS;
    }
}
