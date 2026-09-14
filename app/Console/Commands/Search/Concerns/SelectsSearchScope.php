<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SelectsSearchScope.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Console\Commands\Search\Concerns;

use App\Enums\Search\SearchSourceType;
use App\Services\Search\Indexing\SearchSourceRegistry;
use App\Services\Search\Indexing\Sources\SearchSource;

/**
 * Optionen `--organization` und `--type` der Index-Befehle. Ohne
 * Organisation laufen sie bewusst über alle Mandanten (Konsolen-Vertrag).
 */
trait SelectsSearchScope {
    /** @return int|false|null  false = ungültige Eingabe */
    private function organizationOption(): int|false|null {
        $value = $this->option('organization');
        if ($value === null || $value === '') {
            return null;
        }
        if (! ctype_digit((string) $value)) {
            $this->error('--organization erwartet eine numerische ID.');

            return false;
        }

        return (int) $value;
    }

    /** @return list<SearchSource>|null  null = ungültige Quelle */
    private function selectedSources(SearchSourceRegistry $registry): ?array {
        $requested = (array) $this->option('type');
        if ($requested === []) {
            return $registry->all();
        }

        $sources = [];
        foreach ($requested as $value) {
            $type = SearchSourceType::tryFrom((string) $value);
            if ($type === null) {
                $this->error('Unbekannte Quelle: ' . $value);

                return null;
            }
            $sources[] = $registry->get($type);
        }

        return $sources;
    }
}
