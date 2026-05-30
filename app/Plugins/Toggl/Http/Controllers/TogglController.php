<?php
/*
 * Created on   : Wed May 27 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TogglController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Toggl\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{Customer, Organization, Project, User};
use App\Plugins\Toggl\{TogglConfig, TogglImportService};
use App\Support\Sqid;
use Carbon\CarbonImmutable;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin-Seite für den Toggl-Import: API-Sync auslösen, Detailed-Report-CSV
 * hochladen und die Inbox unzugeordneter Einträge bearbeiten (einem Kunden +
 * Projekt zuweisen oder verwerfen).
 */
class TogglController extends Controller {
    public function __construct(private readonly TogglImportService $service) {
    }

    public function index(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        $groups = $organization !== null ? $this->service->openPendingGroups($organization) : collect();

        $customers = Customer::query()->orderBy('name')->get(['id', 'name', 'company']);
        $projects = Project::query()
            ->whereNotNull('customer_id')
            ->orderBy('name')
            ->get(['id', 'name', 'customer_id']);

        return view('toggl::admin.import', [
            'groups' => $groups,
            'customers' => $customers,
            'projects' => $projects,
        ]);
    }

    public function sync(): RedirectResponse {
        $admin = $this->admin();

        $config = TogglConfig::resolve($admin->organization_id);
        if ($config['api_token'] === null) {
            return back()->withErrors(['api_token' => __('Kein Toggl API-Token hinterlegt.')]);
        }

        $to = CarbonImmutable::now();
        $from = $to->subDays(max(1, (int) $config['sync_window_days']));

        $result = $this->service->importFromApi($this->organization($admin), $config, $from, $to);

        return back()->with('status', $this->importMessage($result));
    }

    public function uploadCsv(Request $request): RedirectResponse {
        $admin = $this->admin();

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $content = (string) file_get_contents($request->file('csv')->getRealPath());
        $config = TogglConfig::resolve($admin->organization_id);

        $result = $this->service->importFromCsv($this->organization($admin), $content, $config);

        return back()->with('status', $this->importMessage($result));
    }

    public function assign(Request $request): RedirectResponse {
        $admin = $this->admin();

        $rawProjectId = $request->input('project_id');
        $projectId = Sqid::decode(Project::class, $rawProjectId);
        if ($projectId === null && is_numeric($rawProjectId)) {
            $projectId = (int) $rawProjectId;
        }

        $request->merge([
            'project_id' => $projectId,
        ]);

        $validated = $request->validate([
            'client_name' => ['nullable', 'string', 'max:191'],
            'project_name' => ['nullable', 'string', 'max:191'],
            'project_id' => ['required', 'integer'],
        ]);

        $project = Project::query()->whereKey($validated['project_id'])->firstOrFail();
        $customer = $project->customer;
        abort_unless($customer instanceof Customer, 422, __('Das gewählte Projekt hat keinen Kunden.'));

        $result = $this->service->assignPending(
            $this->organization($admin),
            $validated['client_name'] ?? null,
            $validated['project_name'] ?? null,
            $customer,
            $project,
        );

        return back()->with('status', (string) __(':created gebucht, :skipped bereits vorhanden.', $result));
    }

    public function dismiss(Request $request): RedirectResponse {
        $admin = $this->admin();

        $validated = $request->validate([
            'client_name' => ['nullable', 'string', 'max:191'],
            'project_name' => ['nullable', 'string', 'max:191'],
        ]);

        $count = $this->service->dismissPending(
            $this->organization($admin),
            $validated['client_name'] ?? null,
            $validated['project_name'] ?? null,
        );

        return back()->with('status', (string) __(':count Eintrag/Einträge verworfen.', ['count' => $count]));
    }

    /** @param array{created: int, skipped: int, unmatched: int} $result */
    private function importMessage(array $result): string {
        return (string) __(':created gebucht, :skipped übersprungen, :unmatched in der Inbox.', $result);
    }

    private function admin(): User {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->organization_id !== null, 422, 'Kein Organisationskontext.');

        return $user;
    }

    private function organization(User $admin): Organization {
        $org = $admin->organization;
        abort_unless($org instanceof Organization, 422, 'Kein Organisationskontext.');

        return $org;
    }
}
