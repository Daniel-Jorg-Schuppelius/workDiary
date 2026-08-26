<?php
/*
 * Created on   : Tue Aug 25 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IsolatesAuditChainProofs.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace Tests\Concerns;

/**
 * Die Rechain-Migrationen legen ihren GoBD-Nachweis unter
 * `storage/app/audit-chain-*.jsonl` ab. Tests, die diese Migrationen laufen
 * lassen, dürfen dafür nicht das echte storage/ verwenden — sonst sammeln sich
 * dort mit jedem Testlauf (und jedem Parallel-Worker) Nachweisdateien an; genau
 * so entstanden bis August 2026 rund 250 Stück (MVP-723).
 *
 * Der Storage-Pfad zeigt deshalb je Test auf ein eigenes temporäres
 * Verzeichnis. Das isoliert die Nachweise zugleich gegen andere Worker —
 * „genau eine Datei" ist dadurch überhaupt erst zuverlässig prüfbar.
 */
trait IsolatesAuditChainProofs {
    private ?string $originalStoragePath = null;

    private ?string $proofStoragePath = null;

    protected function isolateAuditChainProofs(): void {
        $this->originalStoragePath = $this->app->storagePath();
        $this->proofStoragePath = sys_get_temp_dir() . '/wd-audit-proofs-' . bin2hex(random_bytes(8));
        mkdir($this->proofStoragePath . '/app', 0775, true);
        $this->app->useStoragePath($this->proofStoragePath);
    }

    protected function releaseAuditChainProofs(): void {
        if ($this->originalStoragePath !== null) {
            $this->app?->useStoragePath($this->originalStoragePath);
            $this->originalStoragePath = null;
        }

        if ($this->proofStoragePath !== null) {
            foreach ($this->auditChainProofs() as $file) {
                @unlink($file);
            }
            @rmdir($this->proofStoragePath . '/app');
            @rmdir($this->proofStoragePath);
            $this->proofStoragePath = null;
        }
    }

    /**
     * Geschriebene Nachweisdateien des laufenden Tests.
     *
     * @return list<string>
     */
    protected function auditChainProofs(): array {
        $files = glob(($this->proofStoragePath ?? '') . '/app/audit-chain-*.jsonl') ?: [];
        sort($files);

        return array_values($files);
    }
}
