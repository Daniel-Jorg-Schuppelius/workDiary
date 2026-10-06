<?php
/*
 * Created on   : Sun Oct 04 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : TimeImportAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

declare(strict_types=1);

namespace App\Plugins\Support\TimeTracking;

use App\Http\Controllers\Controller;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Platform\Organization;
use App\Plugins\Support\AbstractTimeEntryPushService;
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use Carbon\CarbonImmutable;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Admin-Seite einer Zeiterfassung mit CSV-Upload, API-Import und
 * Rückübertragung. Zugeordnetes wird sofort als TimeEntry angelegt;
 * Unzugeordnetes landet in der Zuordnungs-Inbox — hier nur die Anzahl offener
 * Gruppen als Hinweis.
 */
abstract class TimeImportAdminController extends Controller {
    use ResolvesPluginOrgContext;

    abstract protected function pluginId(): string;

    /** @return view-string */
    abstract protected function view(): string;

    /** @return array<string, mixed> */
    abstract protected function config(?int $organizationId): array;

    /** @param  array<string, mixed>  $config */
    abstract protected function apiConfigured(array $config): bool;

    abstract protected function importer(): CsvAndApiTimeImporter;

    abstract protected function exporter(): AbstractTimeEntryPushService;

    /** @param  array{created: int, skipped: int, unmatched: int}  $counts */
    abstract protected function importedMessage(array $counts, bool $viaApi): string;

    /** @param  array{pushed: int, skipped: int, failed: int}  $counts */
    abstract protected function exportedMessage(array $counts): string;

    public function index(): View {
        $admin = $this->admin();
        $organization = $admin->organization;
        $config = $this->config($admin->organization_id);

        return view($this->view(), [
            'inboxOpenCount' => $organization instanceof Organization
                ? IntegrationInboxItem::openCount((int) $organization->id, $this->pluginId(), grouped: true)
                : 0,
            'apiConfigured' => $this->apiConfigured($config),
            'exportEnabled' => (bool) ($config['export_enabled'] ?? false),
            'syncWindowDays' => $config['sync_window_days'] ?? null,
        ]);
    }

    public function uploadCsv(Request $request): RedirectResponse {
        $admin = $this->admin();

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $content = ToolkitFile::read((string) $request->file('csv')->getRealPath());
        $result = $this->importer()->importFromCsv($this->organization($admin), $content, $this->config($admin->organization_id));

        return back()->with('status', $this->importedMessage($this->importCounts($result), false) . $this->unresolvedUsersSuffix($result));
    }

    /** API-Import über das Formular-Zeitfenster (leer = sync_window_days rückwirkend). */
    public function importApi(Request $request): RedirectResponse {
        $admin = $this->admin();
        [$from, $to] = $this->window($request);

        $result = $this->importer()->importFromApi($this->organization($admin), $this->config($admin->organization_id), $from, $to);

        if (isset($result['error'])) {
            return back()->withErrors(['api' => $result['error']]);
        }

        return back()->with('status', $this->importedMessage($this->importCounts($result), true) . $this->unresolvedUsersSuffix($result));
    }

    public function exportApi(Request $request): RedirectResponse {
        $admin = $this->admin();
        [$from, $to] = $this->window($request);

        $result = $this->exporter()->exportPending($this->organization($admin), $this->config($admin->organization_id), $from, $to);

        if ($result['pushed'] === 0 && $result['errors'] !== []) {
            return back()->withErrors(['api' => $result['errors'][0]]);
        }

        $status = $this->exportedMessage(['pushed' => $result['pushed'], 'skipped' => $result['skipped'], 'failed' => $result['failed']]);
        if ($result['errors'] !== []) {
            $status .= ' ' . $result['errors'][0];
        }

        return back()->with('status', $status);
    }

    /** @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable} */
    private function window(Request $request): array {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return [
            isset($data['from']) ? CarbonImmutable::parse((string) $data['from'])->startOfDay() : null,
            isset($data['to']) ? CarbonImmutable::parse((string) $data['to'])->endOfDay() : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array{created: int, skipped: int, unmatched: int}
     */
    private function importCounts(array $result): array {
        return [
            'created' => (int) ($result['created'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'unmatched' => (int) ($result['unmatched'] ?? 0),
        ];
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
