<?php
/*
 * Created on   : Thu Jul 31 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : FritzboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Fritzbox\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{IntegrationInboxItem, Organization};
use App\Plugins\Fritzbox\{FritzboxConfig, FritzboxImportService, FritzboxPlugin};
use App\Plugins\Support\Concerns\ResolvesPluginOrgContext;
use CommonToolkit\Helper\FileSystem\File as ToolkitFile;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\View\View;

/**
 * Admin-Seite für den FRITZ!Box-Anruflisten-Import: CSV hochladen. Telefonate
 * bekannter Nummern werden sofort gebucht bzw. mit überlappenden Fernwartungs-
 * zeiten verschmolzen; unbekannte Nummern landen in der universellen
 * Zuordnungs-Inbox (admin.integration.inbox) — hier nur die Anzahl offener
 * Gruppen als Deep-Link-Hinweis.
 */
class FritzboxController extends Controller {
    use ResolvesPluginOrgContext;

    public function __construct(private readonly FritzboxImportService $service) {}

    public function index(): View {
        $admin = $this->admin();
        $organization = $admin->organization;

        $inboxOpenCount = $organization instanceof Organization
            ? IntegrationInboxItem::query()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', FritzboxPlugin::ID)
                ->where('status', IntegrationInboxItem::STATUS_OPEN)
                ->whereNotNull('group_key')
                ->count()
            : 0;

        $config = FritzboxConfig::resolve($admin->organization_id);

        return view('fritzbox::admin.import', [
            'inboxOpenCount' => $inboxOpenCount,
            'minCallMinutes' => $config['min_call_minutes'],
            'leadMinutes' => $config['call_lead_minutes'],
        ]);
    }

    public function uploadCsv(Request $request): RedirectResponse {
        $admin = $this->admin();

        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:20480'],
        ]);

        $content = ToolkitFile::read((string) $request->file('csv')->getRealPath());
        $config = FritzboxConfig::resolve($admin->organization_id);

        try {
            $result = $this->service->importFromCsv($this->organization($admin), $content, $config);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['csv' => $e->getMessage()]);
        }

        return back()->with('status', __('FRITZ!Box-Import: :created gebucht, :linked verschmolzen, :pending offen (Inbox), :skipped übersprungen, :ignored ausgefiltert, :locked gesperrt.', [
            'created' => $result['created'],
            'linked' => $result['linked'],
            'pending' => $result['pending'],
            'skipped' => $result['skipped'],
            'ignored' => $result['ignored'],
            'locked' => $result['locked'],
        ]));
    }
}
