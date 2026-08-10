<?php
/*
 * Created on   : Tue Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : ClockifyController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Clockify\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{IntegrationInboxItem, Organization};
use App\Plugins\Clockify\{ClockifyConfig, ClockifyImportService, ClockifyPlugin};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Admin-Seite für den Clockify-Import: Detailed-Report-CSV hochladen oder
 * direkt über die Reports-API importieren. Zugeordnetes wird sofort als
 * TimeEntry angelegt; Unzugeordnetes landet in der universellen
 * Zuordnungs-Inbox (admin.integration.inbox) — hier nur die Anzahl offener
 * Gruppen als Deep-Link-Hinweis.
 */
class ClockifyController extends Controller {
    use ResolvesPluginOrgContext;

    public function __construct(private readonly ClockifyImportService $service) {}

    public function index(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        $inboxOpenCount = $organization instanceof Organization
            ? IntegrationInboxItem::query()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', ClockifyPlugin::ID)
                ->where('status', IntegrationInboxItem::STATUS_OPEN)
                ->whereNotNull('group_key')
                ->count()
            : 0;

        $config = ClockifyConfig::resolve($admin->organization_id);

        return view('clockify::admin.import', [
            'inboxOpenCount' => $inboxOpenCount,
            'apiConfigured' => $config['api_key'] !== null,
            'syncWindowDays' => $config['sync_window_days'],
        ]);
    }

    public function uploadCsv(Request $request): RedirectResponse {
        $admin = $this->admin();

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $content = ToolkitFile::read((string) $request->file('csv')->getRealPath());
        $config = ClockifyConfig::resolve($admin->organization_id);

        $result = $this->service->importFromCsv($this->organization($admin), $content, $config);

        return back()->with('status', __('Clockify-Import: :created angelegt, :skipped übersprungen, :unmatched offen (Inbox).', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'unmatched' => $result['unmatched'],
        ]) . $this->unresolvedUsersSuffix($result));
    }

    /** API-Import über das Formular-Zeitfenster (leer = sync_window_days rückwirkend). */
    public function importApi(Request $request): RedirectResponse {
        $admin = $this->admin();

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $config = ClockifyConfig::resolve($admin->organization_id);

        $result = $this->service->importFromApi(
            $this->organization($admin),
            $config,
            isset($data['from']) ? CarbonImmutable::parse((string) $data['from'])->startOfDay() : null,
            isset($data['to']) ? CarbonImmutable::parse((string) $data['to'])->endOfDay() : null,
        );

        if (isset($result['error'])) {
            return back()->withErrors(['api' => $result['error']]);
        }

        return back()->with('status', __('Clockify-API-Import: :created angelegt, :skipped übersprungen, :unmatched offen (Inbox).', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'unmatched' => $result['unmatched'],
        ]) . $this->unresolvedUsersSuffix($result));
    }

    /**
     * Hinweis auf Einträge ohne zuordenbaren Quell-Benutzer (MVP-509).
     *
     * @param  array<string, mixed>  $result
     */
    private function unresolvedUsersSuffix(array $result): string {
        $n = (int) ($result['unresolved_users'] ?? 0);

        return $n > 0
            ? ' ' . __(':n ohne zuordenbaren Benutzer — Fälle liegen in der Integrations-Inbox.', ['n' => $n])
            : '';
    }
}
