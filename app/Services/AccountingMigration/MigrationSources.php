<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MigrationSources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AccountingMigration;

use App\Enums\Migration\MigrationProvider;
use App\Services\AccountingMigration\Contracts\MigrationSource;
use RuntimeException;

final class MigrationSources {
    /** @var array<string, MigrationSource> */
    private array $sources = [];

    public function register(MigrationSource $source): void {
        $this->sources[$source->provider()->value] = $source;
    }

    public function for(MigrationProvider $provider): MigrationSource {
        return $this->sources[$provider->value]
            ?? throw new RuntimeException('Quellsystem ' . $provider->value . ' ist für den Buchhaltungswechsel nicht verfügbar.');
    }
}
