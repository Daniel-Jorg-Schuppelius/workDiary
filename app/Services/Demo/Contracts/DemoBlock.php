<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoBlock.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Demo\Contracts;

use App\Models\Platform\Organization;

/**
 * Erweiterungspunkt der Demo-Daten (MVP-863): Ein Fachmodul liefert seinen
 * Demo-Block selbst und meldet ihn über `Manifest::extensions()`. Der
 * Demo-Seeder kennt kein Fachmodul; er ruft nur Blöcke, die den Kontext
 * unterstützen (Musterbranche, aktive Module).
 */
interface DemoBlock {
    public function supports(DemoSeedContext $context): bool;

    /** @return array<string, int> Zähler, werden 1:1 in die Seed-Zähler übernommen */
    public function seed(DemoSeedContext $context): array;

    /** Entfernt die eigenen Demo-Daten der Organisation (läuft vor dem generischen Reset). */
    public function purge(Organization $organization): void;
}
