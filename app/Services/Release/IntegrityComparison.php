<?php
/*
 * Created on   : Tue Jul 21 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : IntegrityComparison.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Release;

/**
 * Diff eines Integritätslaufs gegen die Baseline (Feature 095):
 * dateiweise Befunde (Pfadlisten) + veränderte vendor-Pakete + Befunde der
 * Signatur-/Artefaktkette. Listen sind ungedeckelt — Deckelung erst bei
 * Persistenz/Ausgabe ({@see CodeIntegrityService::cappedFindings()}).
 */
final class IntegrityComparison {
    /**
     * @param  list<string>  $added  Pfade, die nicht in der Baseline stehen
     * @param  list<string>  $modified  Pfade mit abweichendem Hash
     * @param  list<string>  $deleted  Baseline-Pfade, die fehlen
     * @param  list<string>  $packages  vendor-Pakete mit abweichendem Aggregat (inkl. neu/entfernt)
     * @param  list<string>  $chain  Befunde der Signatur-/Artefaktkette (ReleaseVerifier)
     */
    public function __construct(
        public readonly array $added = [],
        public readonly array $modified = [],
        public readonly array $deleted = [],
        public readonly array $packages = [],
        public readonly array $chain = [],
    ) {}

    public function clean(): bool {
        return $this->added === [] && $this->modified === [] && $this->deleted === []
            && $this->packages === [] && $this->chain === [];
    }

    /**
     * Stabile Zustands-Identität des Befund-Sets (für Zustandswechsel-Alarme):
     * gleiche Abweichungsmenge ⇒ gleicher Hash, unabhängig von Lauf/Zeit.
     */
    public function findingsHash(): string {
        return \CommonToolkit\Helper\Data\CryptoHelper::hash(ReleaseManifestService::canonicalJson([
            'added' => $this->added,
            'modified' => $this->modified,
            'deleted' => $this->deleted,
            'packages' => $this->packages,
            'chain' => $this->chain,
        ]));
    }
}
