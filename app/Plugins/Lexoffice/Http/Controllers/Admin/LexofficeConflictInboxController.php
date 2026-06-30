<?php
/*
 * Created on   : Tue May 26 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : LexofficeConflictInboxController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\Lexoffice\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Customer, PendingExternalConflict, User};
use App\Plugins\Lexoffice\LexofficePlugin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Admin-Inbox für offene Lexoffice-Konflikte aus dem Pull-Sync (Policy = manual_review).
 *
 * Pro Konflikt kann der Admin entscheiden:
 *  - resolve_local: lokale Werte gewinnen, Konflikt wird geschlossen ohne Änderung
 *  - resolve_remote: Remote-Werte werden in den Kunden übernommen
 *  - dismiss: Konflikt wird ignoriert (z. B. bewusst abweichende Daten)
 */
class LexofficeConflictInboxController extends Controller {
    /**
     * Die Lexoffice-Konflikte sind in die universelle Zuordnungs-Inbox
     * (MVP-103) umgezogen. Diese Route leitet dorthin um — der Controller bleibt
     * nur für die Auflösung etwaiger Alt-Konflikte (resolve/dismiss) erhalten.
     */
    public function index(): RedirectResponse {
        /** @var User $admin */
        $admin = Auth::user();
        abort_unless($admin->canManageBilling(), 403);

        return redirect()->route('admin.integration.inbox', ['plugin' => LexofficePlugin::ID, 'case' => 'conflict']);
    }

    public function resolveLocal(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeAccess($conflict);
        $conflict->update([
            'status' => PendingExternalConflict::STATUS_RESOLVED_LOCAL,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return back()->with('success', __('Konflikt zugunsten der lokalen Daten geschlossen.'));
    }

    public function resolveRemote(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeAccess($conflict);
        /** @var Customer|null $customer */
        $customer = $conflict->referenceable;
        if ($customer instanceof Customer) {
            $allowed = ['name', 'company', 'vat_id', 'email', 'phone', 'address_street', 'address_zip', 'address_city', 'country'];
            $remote = $conflict->remote_snapshot;
            $changes = [];
            foreach ($conflict->diff_fields ?? [] as $field) {
                if (! in_array($field, $allowed, true)) {
                    continue;
                }
                $changes[$field] = $this->resolveRemoteField($remote, $field);
            }
            if ($changes !== []) {
                $customer->fill($changes)->save();
            }
        }

        $conflict->update([
            'status' => PendingExternalConflict::STATUS_RESOLVED_REMOTE,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return back()->with('success', __('Konflikt zugunsten der Lexoffice-Daten gelöst, Kunde aktualisiert.'));
    }

    public function dismiss(PendingExternalConflict $conflict): RedirectResponse {
        $this->authorizeAccess($conflict);
        $conflict->update([
            'status' => PendingExternalConflict::STATUS_DISMISSED,
            'resolved_by' => Auth::id(),
            'resolved_at' => now(),
        ]);

        return back()->with('success', __('Konflikt verworfen.'));
    }

    private function authorizeAccess(PendingExternalConflict $conflict): void {
        /** @var User $admin */
        $admin = Auth::user();
        abort_unless($admin->canManageBilling(), 403);
        abort_unless($conflict->organization_id === $admin->organization_id, 404);
    }

    /**
     * Übersetzt die verschachtelten Lexoffice-Felder in das flache Customer-Schema.
     *
     * @param  array<string, mixed>  $remote
     */
    private function resolveRemoteField(array $remote, string $field): ?string {
        return match ($field) {
            'name' => trim(((string) data_get($remote, 'person.firstName', '')) . ' ' . ((string) data_get($remote, 'person.lastName', ''))) ?: (string) data_get($remote, 'company.name', ''),
            'company' => (string) data_get($remote, 'company.name', '') ?: null,
            'vat_id' => (string) data_get($remote, 'company.vatRegistrationId', '') ?: (string) data_get($remote, 'company.taxNumber', '') ?: null,
            'email' => (string) data_get($remote, 'emailAddresses.business.0', '') ?: null,
            'phone' => (string) data_get($remote, 'phoneNumbers.business.0', '') ?: null,
            'address_street' => (string) data_get($remote, 'addresses.billing.0.street', '') ?: null,
            'address_zip' => (string) data_get($remote, 'addresses.billing.0.zip', '') ?: null,
            'address_city' => (string) data_get($remote, 'addresses.billing.0.city', '') ?: null,
            'country' => (string) data_get($remote, 'addresses.billing.0.countryCode', '') ?: null,
            default => null,
        };
    }
}
