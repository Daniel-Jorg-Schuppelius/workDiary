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
use Illuminate\Console\Command;

/**
 * Optionen `--organization` und `--type` der Index-Befehle. Befehle ohne
 * `--type` binden nur {@see SelectsOrganization} ein — Larastan prüft die
 * Trait-Methoden je Kommando gegen dessen Signatur.
 *
 * @mixin Command
 */
trait SelectsSearchScope {
    use SelectsOrganization;

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
