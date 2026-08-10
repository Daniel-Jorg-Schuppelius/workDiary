<?php
/*
 * Created on   : Mon Jul 07 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : KimaiController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Kimai\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{IntegrationInboxItem, Organization};
use App\Plugins\Kimai\{KimaiConfig, KimaiExportService, KimaiImportService, KimaiPlugin};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Admin-Seite für Kimai-Import/-Export: Timesheet-CSV hochladen, direkt aus der
 * Kimai-API importieren oder erfasste Zeiten zurückbuchen. Zugeordnetes wird
 * sofort als TimeEntry angelegt; Unzugeordnetes landet in der universellen
 * Zuordnungs-Inbox (admin.integration.inbox) — hier nur die Anzahl offener
 * Gruppen als Deep-Link-Hinweis.
 */
class KimaiController extends Controller {
    use ResolvesPluginOrgContext;

    public function __construct(private readonly KimaiImportService $service) {}

    public function index(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        $inboxOpenCount = $organization instanceof Organization
            ? IntegrationInboxItem::query()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', KimaiPlugin::ID)
                ->where('status', IntegrationInboxItem::STATUS_OPEN)
                ->whereNotNull('group_key')
                ->count()
            : 0;

        $config = KimaiConfig::resolve($admin->organization_id);

        return view('kimai::admin.import', [
            'inboxOpenCount' => $inboxOpenCount,
            'apiConfigured' => $config['api_token'] !== null && $config['base_url'] !== null,
            'exportEnabled' => $config['export_enabled'],
            'syncWindowDays' => $config['sync_window_days'],
        ]);
    }

    /** API-Import über das Formular-Zeitfenster (leer = sync_window_days rückwirkend). */
    public function importApi(Request $request): RedirectResponse {
        $admin = $this->admin();

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $config = KimaiConfig::resolve($admin->organization_id);

        $result = $this->service->importFromApi(
            $this->organization($admin),
            $config,
            isset($data['from']) ? CarbonImmutable::parse((string) $data['from'])->startOfDay() : null,
            isset($data['to']) ? CarbonImmutable::parse((string) $data['to'])->endOfDay() : null,
        );

        if (isset($result['error'])) {
            return back()->withErrors(['api' => $result['error']]);
        }

        return back()->with('status', __('Kimai-API-Import: :created angelegt, :skipped übersprungen, :unmatched offen (Inbox).', [
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'unmatched' => $result['unmatched'],
        ]) . $this->unresolvedUsersSuffix($result));
    }

    /** Rückbuchung erfasster Zeiten gemappter Projekte als Kimai-Timesheets. */
    public function exportApi(Request $request, KimaiExportService $export): RedirectResponse {
        $admin = $this->admin();

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $config = KimaiConfig::resolve($admin->organization_id);

        $result = $export->exportPending(
            $this->organization($admin),
            $config,
            isset($data['from']) ? CarbonImmutable::parse((string) $data['from'])->startOfDay() : null,
            isset($data['to']) ? CarbonImmutable::parse((string) $data['to'])->endOfDay() : null,
        );

        if ($result['pushed'] === 0 && $result['errors'] !== []) {
            return back()->withErrors(['api' => $result['errors'][0]]);
        }

        $status = __('Kimai-Export: :pushed gebucht, :skipped übersprungen, :failed fehlgeschlagen.', [
            'pushed' => $result['pushed'],
            'skipped' => $result['skipped'],
            'failed' => $result['failed'],
        ]);
        if ($result['errors'] !== []) {
            $status .= ' ' . $result['errors'][0];
        }

        return back()->with('status', $status);
    }

    public function uploadCsv(Request $request): RedirectResponse {
        $admin = $this->admin();

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $content = ToolkitFile::read((string) $request->file('csv')->getRealPath());
        $config = KimaiConfig::resolve($admin->organization_id);

        $result = $this->service->importFromCsv($this->organization($admin), $content, $config);

        return back()->with('status', __('Kimai-Import: :created angelegt, :skipped übersprungen, :unmatched offen (Inbox).', [
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
