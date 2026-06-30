<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglGroupBooker.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Toggl;

use App\Models\Organization;
use App\Services\Integration\Concerns\ResolvesInboxTargets;
use App\Services\Integration\InboxGroupBooker;
use Illuminate\Support\Collection;

/**
 * Bindet die gruppierten Toggl-Zeit-Import-Einträge an die universelle
 * Zuordnungs-Inbox: liefert die offenen Gruppen samt Fuzzy-Vorschlägen, löst
 * Kunde + Projekt (existierend-oder-neu) auf und delegiert Buchung/Verwerfen an
 * den {@see TogglImportService}.
 */
class TogglGroupBooker implements InboxGroupBooker {
    use ResolvesInboxTargets;

    public function __construct(private readonly TogglImportService $service) {}

    public function groups(Organization $organization): Collection {
        /** @var Collection<int, array<string, mixed>> $groups */
        $groups = $this->service->openInboxGroups($organization)->map(function (array $group) use ($organization): array {
            $customer = $this->service->suggestCustomer($organization, $group['client_name']);
            $project = $this->service->suggestProject($organization, $customer, $group['project_name']);

            return [
                'plugin_id' => TogglPlugin::ID,
                'form' => 'customer_project',
                'group_key' => $group['group_key'],
                'client_name' => $group['client_name'],
                'project_name' => $group['project_name'],
                'count' => $group['count'],
                'minutes' => $group['minutes'],
                'first_seen' => $group['first_seen'],
                'last_seen' => $group['last_seen'],
                'suggested_customer_sqid' => $customer?->sqid,
                'suggested_project_sqid' => $project?->sqid,
            ];
        })->values();

        return $groups;
    }

    public function rules(): array {
        return [
            'customer_mode' => ['required', 'in:existing,new'],
            'customer' => ['nullable', 'string', 'required_if:customer_mode,existing'],
            'new_customer_name' => ['nullable', 'string', 'max:191', 'required_if:customer_mode,new'],
            'project_mode' => ['required', 'in:existing,new'],
            'project' => ['nullable', 'string', 'required_if:project_mode,existing'],
            'new_project_name' => ['nullable', 'string', 'max:191', 'required_if:project_mode,new'],
        ];
    }

    public function book(Organization $organization, string $groupKey, array $input): array {
        $customer = $this->resolveCustomerTarget($organization, $input);
        $project = $this->resolveProjectUnderCustomer($organization, $customer, $input);

        return $this->service->bookInboxGroup($organization, $groupKey, $customer, $project);
    }

    public function dismiss(Organization $organization, string $groupKey): int {
        return $this->service->dismissInboxGroup($organization, $groupKey);
    }
}
