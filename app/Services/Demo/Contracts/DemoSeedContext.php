<?php
/*
 * Created on   : Thu Sep 24 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : DemoSeedContext.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Demo\Contracts;

use App\Enums\Demo\DemoIndustry;
use App\Models\Customer\Customer;
use App\Models\Diary\DiaryEntry;
use App\Models\Platform\{Organization, User};
use App\Models\Project\Project;
use Illuminate\Support\Collection;

/** Was ein {@see DemoBlock} vom Seed-Lauf wissen darf. */
final readonly class DemoSeedContext {
    /**
     * @param  Collection<int, User>  $users  Demo-Nutzer in Seed-Reihenfolge (Admin, Operator A, Operator B, Disponent, …)
     * @param  array<string, mixed>  $blueprint  Branchen-Blueprint
     * @param  list<string>|null  $activeModules  Lizenzcodes des Showcase-Umfangs, null = alle
     * @param  Collection<int, Project>  $projects  Demo-Projekte (Hauptprojekt zuerst)
     */
    public function __construct(
        public Organization $organization,
        public User $actor,
        public Collection $users,
        public array $blueprint,
        public ?DemoIndustry $industry,
        public ?array $activeModules,
        public ?DiaryEntry $mainDiary,
        public ?Customer $mainCustomer = null,
        public Collection $projects = new Collection,
    ) {}

    public function moduleActive(string $licenseCode): bool {
        return $this->activeModules === null || in_array($licenseCode, $this->activeModules, true);
    }
}
