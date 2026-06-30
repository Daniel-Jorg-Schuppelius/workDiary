<?php
/*
 * Created on   : Mon Jun 29 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesInboxTargets.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Services\Integration\Concerns;

use App\Enums\Project\ProjectStatus;
use App\Models\{Customer, Organization, Project};
use Illuminate\Support\Facades\Auth;

/**
 * Gemeinsame Ziel-Auflösung für {@see \App\Services\Integration\InboxGroupBooker}-
 * Implementierungen: Kunde bzw. Projekt aus der validierten Eingabe — bestehend
 * (per Sqid, mandanten-gescopt) oder explizit neu angelegt. „Nie blind anlegen":
 * Neuanlage nur bei expliziter Wahl (`*_mode = new`).
 */
trait ResolvesInboxTargets {
    /**
     * @param  array<string, mixed>  $input
     */
    protected function resolveCustomerTarget(Organization $organization, array $input): Customer {
        if (($input['customer_mode'] ?? null) === 'new') {
            return Customer::query()->create([
                'organization_id' => $organization->id,
                'name' => trim((string) ($input['new_customer_name'] ?? '')),
                'created_by' => Auth::id(),
            ]);
        }

        $customer = (new Customer)->resolveRouteBinding((string) ($input['customer'] ?? ''));
        abort_unless($customer instanceof Customer, 404);
        abort_unless((int) $customer->organization_id === (int) $organization->id, 404);

        return $customer;
    }

    /**
     * Projekt unter einem bestimmten Kunden (bestehend muss zum Kunden gehören).
     *
     * @param  array<string, mixed>  $input
     */
    protected function resolveProjectUnderCustomer(Organization $organization, Customer $customer, array $input): Project {
        if (($input['project_mode'] ?? null) === 'new') {
            return Project::query()->create([
                'organization_id' => $organization->id,
                'customer_id' => $customer->id,
                'name' => trim((string) ($input['new_project_name'] ?? '')),
                'status' => ProjectStatus::Active->value,
                'is_default' => false,
                'created_by' => Auth::id(),
            ]);
        }

        $project = $this->resolveExistingProject($organization, $input);
        abort_unless((int) $project->customer_id === (int) $customer->id, 422, __('Das gewählte Projekt gehört nicht zum gewählten Kunden.'));

        return $project;
    }

    /**
     * Eigenständiges Projekt (kundenlos erlaubt; z. B. OpenProject).
     *
     * @param  array<string, mixed>  $input
     */
    protected function resolveStandaloneProject(Organization $organization, array $input): Project {
        if (($input['project_mode'] ?? null) === 'new') {
            return Project::query()->create([
                'organization_id' => $organization->id,
                'name' => trim((string) ($input['new_project_name'] ?? '')),
                'status' => ProjectStatus::Active->value,
                'is_default' => false,
                'created_by' => Auth::id(),
            ]);
        }

        return $this->resolveExistingProject($organization, $input);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveExistingProject(Organization $organization, array $input): Project {
        $project = (new Project)->resolveRouteBinding((string) ($input['project'] ?? ''));
        abort_unless($project instanceof Project, 404);
        abort_unless((int) $project->organization_id === (int) $organization->id, 404);

        return $project;
    }
}
