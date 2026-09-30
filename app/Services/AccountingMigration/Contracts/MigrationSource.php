<?php
/*
 * Created on   : Wed Sep 30 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MigrationSource.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\AccountingMigration\Contracts;

use App\Enums\Migration\MigrationProvider;

/**
 * Quellsystem eines Buchhaltungswechsels (MVP-1048): Belegspiegel und
 * Abschlusshindernisse. Plugins tragen sich in
 * {@see \App\Services\AccountingMigration\MigrationSources} ein.
 */
interface MigrationSource {
    public function provider(): MigrationProvider;

    /**
     * Beleghistorie der Organisation im Quellsystem.
     *
     * @param  list<string>  $settledStates  Status, die als ausgeglichen gelten
     * @return iterable<int, array{external_id: string, number: ?string, status: ?string, date: ?string, open_amount: ?float, is_open: bool}>
     */
    public function documents(int $organizationId, array $settledStates): iterable;

    /** Morph-Alias des Belegspiegels (Positionen des Datenbereichs „Belege“). */
    public function documentMorphClass(): string;

    /** @return list<string> Hindernisse vor dem Abschluss des Wechsels */
    public function completionBlockers(int $organizationId): array;
}
