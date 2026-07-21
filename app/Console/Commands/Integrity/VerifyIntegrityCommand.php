<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : VerifyIntegrityCommand.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Integrity;

use App\Enums\Security\IntegrityCheckStatus;
use App\Services\Release\CodeIntegrityService;
use Illuminate\Console\Command;

/**
 * Prüft den Quelltext gegen die Baseline (Feature 095, MVP-440):
 * Diff added/modified/deleted + vendor-Pakete + Signatur-/Artefaktkette.
 * Exit-Codes: 0 = ok, 1 = Abweichung/Fehler, 2 = keine Baseline —
 * geeignet für externes Monitoring (--json für Maschinenlesbarkeit).
 */
class VerifyIntegrityCommand extends Command {
    protected $signature = 'integrity:verify
        {--json : Ergebnis als JSON ausgeben (Monitoring)}
        {--trigger=cli : Auslöser des Laufs (cli|schedule|ui)}';

    protected $description = 'Prüft den Quelltext gegen die Integritäts-Baseline (integrity.json).';

    public function handle(CodeIntegrityService $service): int {
        $trigger = (string) $this->option('trigger');

        if ($trigger === 'schedule' && ! (bool) config('integrity.enabled', true)) {
            $this->info('Integritätsprüfung per INTEGRITY_CHECK_ENABLED deaktiviert — Lauf übersprungen.');

            return self::SUCCESS;
        }

        $check = $service->runVerification($trigger);

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'status' => $check->status->value,
                'baseline_source' => $check->baseline_source,
                'baseline_root' => $check->baseline_root,
                'files_checked' => $check->files_checked,
                'added' => $check->added_count,
                'modified' => $check->modified_count,
                'deleted' => $check->deleted_count,
                'packages_changed' => $check->packages_changed_count,
                'findings' => $check->findings,
                'duration_ms' => $check->duration_ms,
                'ran_at' => $check->ran_at->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->printHuman($check->status, $check);
        }

        return match ($check->status) {
            IntegrityCheckStatus::Ok => self::SUCCESS,
            IntegrityCheckStatus::MissingBaseline => 2,
            default => self::FAILURE,
        };
    }

    private function printHuman(IntegrityCheckStatus $status, \App\Models\IntegrityCheck $check): void {
        if ($status === IntegrityCheckStatus::MissingBaseline) {
            $this->error('Keine Baseline gefunden — zuerst `release:manifest` (Herausgeber) oder `integrity:freeze` (lokal) ausführen.');

            return;
        }

        $this->line(sprintf('Baseline: %s (Root %s…)', (string) $check->baseline_source, substr((string) $check->baseline_root, 0, 16)));
        $this->line(sprintf('Geprüfte Dateien: %d, Dauer: %d ms', $check->files_checked, $check->duration_ms));

        if ($status === IntegrityCheckStatus::Ok) {
            $this->info('Quelltext-Integrität in Ordnung.');

            return;
        }

        $this->error(sprintf(
            'Integrität VERLETZT: %d neu, %d geändert, %d gelöscht, %d Paket(e) abweichend.',
            $check->added_count,
            $check->modified_count,
            $check->deleted_count,
            $check->packages_changed_count,
        ));
        foreach ((array) ($check->findings ?? []) as $category => $paths) {
            $this->line('  [' . $category . ']');
            foreach ((array) $paths as $path) {
                $this->line('    • ' . $path);
            }
        }
    }
}
