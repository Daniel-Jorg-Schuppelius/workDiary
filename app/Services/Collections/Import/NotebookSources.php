<?php
/*
 * Created on   : Tue Sep 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : NotebookSources.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Collections\Import;

use App\Models\Platform\Organization;
use App\Services\Collections\Import\Contracts\NotebookSource;

final class NotebookSources {
    /** @var array<string, NotebookSource> */
    private array $sources = [];

    public function register(NotebookSource $source): void {
        $this->sources[$source->key()] = $source;
    }

    public function get(string $key): ?NotebookSource {
        return $this->sources[$key] ?? null;
    }

    /** @return list<NotebookSource> */
    public function ready(Organization $organization): array {
        return array_values(array_filter($this->sources, static fn (NotebookSource $source): bool => $source->ready($organization)));
    }
}
