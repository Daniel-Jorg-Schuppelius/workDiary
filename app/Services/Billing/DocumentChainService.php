<?php
/*
 * Created on   : Fri Oct 02 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DocumentChainService.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Platform\{Organization, User};
use App\Modules\ModuleRegistry;
use App\Services\Billing\Contracts\DocumentChainSource;
use App\Services\Billing\Dto\DocumentChainItem;
use App\Services\Licensing\ModuleStatusResolver;

/** Sammelt die Quellen der Belegkette (MVP-1057), die der Nutzer sehen darf. */
final class DocumentChainService {
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ModuleStatusResolver $status,
    ) {}

    /** @return list<array{key: string, label: string, icon: string, count: int, items: list<DocumentChainItem>}> */
    public function groups(Organization $organization, User $user, int $limit = 10): array {
        $groups = [];
        foreach ($this->sources($organization, $user) as $source) {
            $count = $source->count($organization);
            $groups[] = [
                'key' => $source->key(),
                'label' => $source->label(),
                'icon' => $source->icon(),
                'count' => $count,
                'items' => $count > 0 && $limit > 0 ? $source->items($organization, $limit) : [],
            ];
        }

        return $groups;
    }

    /** @return list<DocumentChainSource> */
    private function sources(Organization $organization, User $user): array {
        $sources = [];
        foreach ($this->modules->extensions(DocumentChainSource::class) as $class) {
            $source = app($class);
            if (! $source instanceof DocumentChainSource || ! $source->availableFor($user)) {
                continue;
            }
            $module = $this->modules->moduleForRoute($source->routeName());
            if ($module === null || $this->status->isActiveFor($organization, $module)) {
                $sources[] = $source;
            }
        }

        return $sources;
    }
}
