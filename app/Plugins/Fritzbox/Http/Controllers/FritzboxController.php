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
use App\Services\Contacts\ExternalPhoneContactDirectory;
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

        // MVP-534: Stempel-Rufnummern (Rufnummer → Benutzer) + aktive MSNs.
        $stampNumbers = \App\Models\ExternalReference::forPlugin((int) $admin->organization_id, FritzboxPlugin::ID, FritzboxImportService::EXT_TYPE_STAMP_NUMBER)
            ->with('referenceable')
            ->orderBy('external_id')
            ->get();

        return view('fritzbox::admin.import', [
            'inboxOpenCount' => $inboxOpenCount,
            'minCallMinutes' => $config['min_call_minutes'],
            'leadMinutes' => $config['call_lead_minutes'],
            'stampNumbers' => $stampNumbers,
            'stampLinesActive' => array_filter([
                trim($config['stamp_in_line']),
                trim($config['stamp_out_line']),
                trim($config['stamp_toggle_line']),
            ]),
            'stampUserOptions' => \App\Models\User::query()
                ->where('organization_id', $admin->organization_id)
                ->whereNull('deactivated_at')
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (\App\Models\User $u): array => ['sqid' => \App\Support\Sqid::encode(\App\Models\User::class, (int) $u->id), 'name' => (string) $u->name])
                ->values()
                ->all(),
            'contactDirectorySources' => (bool) $config['external_contact_matching']
                ? app(ExternalPhoneContactDirectory::class)->availableSourceLabels($this->organization($admin))
                : [],
        ]);
    }

    /** MVP-534: Stempel-Rufnummer einem Benutzer zuordnen. */
    public function storeStampNumber(Request $request): RedirectResponse {
        $admin = $this->admin();

        $data = $request->validate([
            'user' => ['required', 'string'],
            'number' => ['required', 'string', 'max:40'],
        ]);

        $userId = \App\Support\Sqid::decodeOrNumeric(\App\Models\User::class, (string) $data['user']);
        $user = \App\Models\User::query()
            ->where('organization_id', $admin->organization_id)
            ->find((int) ($userId ?? 0));
        if (! $user instanceof \App\Models\User) {
            return back()->withErrors(['user' => __('Unbekannter Benutzer.')]);
        }

        $e164 = \CommonToolkit\Helper\Data\PhoneNumberHelper::toE164((string) $data['number'], 'DE');
        if ($e164 === null || $e164 === '') {
            return back()->withErrors(['number' => __('Rufnummer konnte nicht normalisiert werden.')]);
        }

        $this->service->rememberStampNumber($this->organization($admin), $e164, $user);

        return back()->with('status', __('Stempel-Rufnummer :number für :name gespeichert.', ['number' => $e164, 'name' => $user->name]));
    }

    /** MVP-534: Stempel-Rufnummer entfernen. */
    public function destroyStampNumber(Request $request): RedirectResponse {
        $admin = $this->admin();

        $data = $request->validate(['number' => ['required', 'string', 'max:40']]);

        \App\Models\ExternalReference::forPlugin((int) $admin->organization_id, FritzboxPlugin::ID, FritzboxImportService::EXT_TYPE_STAMP_NUMBER)
            ->forExternalId((string) $data['number'])
            ->get()->each->delete();

        return back()->with('status', __('Stempel-Rufnummer entfernt.'));
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

        return back()->with('status', __('FRITZ!Box-Import: :created gebucht, :linked verschmolzen, :stamped gestempelt, :pending offen (Inbox), :skipped übersprungen, :ignored ausgefiltert, :locked gesperrt.', [
            'created' => $result['created'],
            'linked' => $result['linked'],
            'stamped' => $result['stamped'],
            'pending' => $result['pending'],
            'skipped' => $result['skipped'],
            'ignored' => $result['ignored'],
            'locked' => $result['locked'],
        ]));
    }
}
