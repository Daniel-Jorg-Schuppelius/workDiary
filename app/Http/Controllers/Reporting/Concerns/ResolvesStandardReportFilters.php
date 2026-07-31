<?php
/*
 * Created on   : Fri Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ResolvesStandardReportFilters.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Reporting\Concerns;

use App\Models\{Customer, EntryType, Project, Team, User};
use App\Services\Reporting\ReportFilters;
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Standard-Filterset der Report-Controller (Feature 002): löst die
 * Query-Parameter customer/project/user/team/entry_type/status Sqid-dekodiert
 * auf und verifiziert jede ID gegen die aktive Organisation (nie rohes
 * exists: — Customer/Project/Team/EntryType tragen den OrganizationScope,
 * User wird explizit über inCurrentOrganization() geprüft).
 *
 * Ungültige oder fremde IDs werden still auf null gesetzt (Semantik wie
 * {@see Sqid::decodeOrNumeric}); der Org-Scope greift zusätzlich auf
 * Query-Ebene, es können also nie Fremddaten erscheinen.
 *
 * Gerendert wird das Set über resources/views/reports/_standard_filters.blade.php.
 */
trait ResolvesStandardReportFilters {
    /**
     * @param  list<string>  $fields  aktivierte Felder: customer|project|user|team|entry_type|status
     * @param  list<string>  $statusValues  erlaubte status-Werte (Bedeutung ist pro Report ein eigenes Enum)
     */
    protected function standardFilters(
        Request $request,
        array $fields,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $statusValues = [],
        string $scope = 'mine',
    ): ReportFilters {
        $customerId = null;
        if (in_array('customer', $fields, true)) {
            $customerId = Sqid::decodeOrNumeric(Customer::class, $request->query('customer'));
            if ($customerId !== null && ! Customer::query()->whereKey($customerId)->exists()) {
                $customerId = null;
            }
        }

        $projectId = null;
        if (in_array('project', $fields, true)) {
            $projectId = Sqid::decodeOrNumeric(Project::class, $request->query('project'));
            if ($projectId !== null) {
                $exists = Project::query()->whereKey($projectId)
                    ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
                    ->exists();
                if (! $exists) {
                    $projectId = null;
                }
            }
        }

        $userId = null;
        if (in_array('user', $fields, true)) {
            $userId = Sqid::decodeOrNumeric(User::class, $request->query('user'));
            if ($userId !== null && ! User::query()->inCurrentOrganization()->whereKey($userId)->exists()) {
                $userId = null;
            }
        }

        $teamId = null;
        if (in_array('team', $fields, true)) {
            $teamId = Sqid::decodeOrNumeric(Team::class, $request->query('team'));
            if ($teamId !== null && ! Team::query()->whereKey($teamId)->exists()) {
                $teamId = null;
            }
        }

        $entryTypeId = null;
        if (in_array('entry_type', $fields, true)) {
            $entryTypeId = Sqid::decodeOrNumeric(EntryType::class, $request->query('entry_type'));
            if ($entryTypeId !== null && ! EntryType::query()->whereKey($entryTypeId)->exists()) {
                $entryTypeId = null;
            }
        }

        $status = null;
        if (in_array('status', $fields, true)) {
            $raw = trim((string) $request->query('status', ''));
            $status = in_array($raw, $statusValues, true) ? $raw : null;
        }

        return new ReportFilters(
            from: $from,
            to: $to,
            customerId: $customerId,
            projectId: $projectId,
            userId: $userId,
            teamId: $teamId,
            entryTypeId: $entryTypeId,
            status: $status,
            scope: $scope,
        );
    }

    /**
     * Optionslisten für das Filter-Partial — nur für aktivierte Felder.
     * Projektliste ist auf den gewählten Kunden eingeschränkt; archivierte
     * Kunden/Projekte/Teams und inaktive Auftragstypen bleiben außen vor.
     *
     * @param  list<string>  $fields
     * @return array{
     *     filterCustomers?: \Illuminate\Database\Eloquent\Collection<int, Customer>,
     *     filterProjects?: \Illuminate\Database\Eloquent\Collection<int, Project>,
     *     filterUsers?: \Illuminate\Database\Eloquent\Collection<int, User>,
     *     filterTeams?: \Illuminate\Database\Eloquent\Collection<int, Team>,
     *     filterEntryTypes?: \Illuminate\Database\Eloquent\Collection<int, EntryType>,
     * }
     */
    protected function standardFilterOptions(array $fields, ?ReportFilters $filters = null): array {
        $options = [];

        if (in_array('customer', $fields, true)) {
            $options['filterCustomers'] = Customer::query()
                ->whereNull('archived_at')->orderBy('name')->get(['id', 'name']);
        }
        if (in_array('project', $fields, true)) {
            $customerId = $filters?->customerId;
            $options['filterProjects'] = Project::query()
                ->whereNull('archived_at')
                ->when($customerId !== null, fn($q) => $q->where('customer_id', $customerId))
                ->orderBy('name')->get(['id', 'name']);
        }
        if (in_array('user', $fields, true)) {
            $options['filterUsers'] = User::query()->inCurrentOrganization()
                ->orderBy('name')->get(['id', 'name']);
        }
        if (in_array('team', $fields, true)) {
            $options['filterTeams'] = Team::query()
                ->whereNull('archived_at')->orderBy('name')->get(['id', 'name']);
        }
        if (in_array('entry_type', $fields, true)) {
            $options['filterEntryTypes'] = EntryType::query()->active()
                ->orderBy('sort')->orderBy('label')->get(['id', 'label']);
        }

        return $options;
    }
}
