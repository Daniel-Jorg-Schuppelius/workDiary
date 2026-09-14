<?php
/*
 * Created on   : Mon Sep 14 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : SearchEngineResolver.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Search\Engine;

use App\Models\SearchDocument;

/** Wählt die Engine: `search.engine` = auto|fulltext|like. */
final class SearchEngineResolver {
    public function compiler(): SearchMatchCompiler {
        $engine = (string) config('search.engine', 'auto');
        if ($engine === 'auto') {
            $driver = (new SearchDocument)->getConnection()->getDriverName();
            $engine = in_array($driver, ['mysql', 'mariadb'], true) ? 'fulltext' : 'like';
        }

        return $engine === 'fulltext' ? new FulltextMatchCompiler : new LikeMatchCompiler;
    }
}
