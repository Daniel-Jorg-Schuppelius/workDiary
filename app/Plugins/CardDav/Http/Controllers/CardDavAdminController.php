<?php
/*
 * Created on   : Sun Jul 12 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : CardDavAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Plugins\CardDav\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\{CardDavCard, CardDavConnection, Organization, User};
use App\Plugins\CardDav\Contracts\CardDavGatewayFactory;
use App\Plugins\CardDav\Services\CardDavAddressbook;
use App\Support\UrlSafety;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Artisan, Auth};
use Illuminate\View\View;
use Throwable;

/**
 * CardDAV-Admin-Panel (Bauturbo A9, MVP-329): eine Leseanbindung je
 * Organisation (Basis-URL, Zugangsdaten verschlüsselt, auditiertes
 * Private-Network-Opt-in), RFC-6764-Discovery mit Adressbuch-Wahl und
 * manueller Sync-Anstoß. Das App-Passwort erscheint nie in Views oder
 * Audit-Payloads ({@see CardDavConnection::$hidden}); ein leeres Passwortfeld
 * beim Speichern lässt das bestehende Passwort unangetastet.
 *
 * SSRF-Leitplanke: private/interne Basis-URLs sind nur mit dem auditierten
 * Schalter `allow_private_network` zulässig (Muster JTL-Wawi). Als Adressbuch
 * ist ausschließlich ein Ergebnis der letzten Discovery wählbar — es lässt
 * sich keine beliebige URL als Sync-Ziel unterschieben.
 */
class CardDavAdminController extends Controller {
    /** Session-Key der zuletzt entdeckten Adressbücher (Discovery-Ergebnis). */
    private const SESSION_BOOKS = 'carddav_addressbooks';

    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CardDavConnection::query()->where('organization_id', $organization->id)->first();

        return view('carddav::admin.index', [
            'connection' => $connection,
            'health' => $connection instanceof CardDavConnection && $connection->isActive()
                ? $this->probe($connection)
                : null,
            'addressbooks' => (array) session(self::SESSION_BOOKS, []),
        ]);
    }

    /** Legt die Anbindung an oder aktualisiert sie (Passwort nur bei Eingabe). */
    public function store(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'base_url' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:190'],
            'app_password' => ['nullable', 'string', 'max:255'],
            'allow_private_network' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ]);

        $baseUrl = rtrim(trim((string) $data['base_url']), '/');
        $allowPrivate = (bool) ($data['allow_private_network'] ?? false);
        if (! str_starts_with($baseUrl, 'http://') && ! str_starts_with($baseUrl, 'https://')) {
            return back()->with('error', __('carddav.flash.invalid_url'))->withInput();
        }
        // Konfigurationszeit-Prüfung ohne DNS (Whitebox 2026-07); die
        // verbindliche Laufzeitprüfung macht CardDavUrlGuard vor jedem Sync.
        if (! $allowPrivate && ! UrlSafety::isAcceptableExternalHttpUrl($baseUrl)) {
            return back()->with('error', __('carddav.flash.private_url_blocked'))->withInput();
        }

        /** @var CardDavConnection $connection */
        $connection = CardDavConnection::query()->firstOrNew(['organization_id' => $organization->id]);

        $attributes = [
            'name' => (string) $data['name'],
            'base_url' => $baseUrl,
            'username' => (string) $data['username'],
            'allow_private_network' => $allowPrivate,
            'active' => (bool) ($data['active'] ?? false),
            'created_by' => $connection->exists ? $connection->created_by : $admin->id,
        ];

        // Passwort nur bei Eingabe setzen — nie leere Strings in encrypted-Felder.
        $password = trim((string) ($data['app_password'] ?? ''));
        if ($password !== '') {
            $attributes['app_password'] = $password;
        } elseif (! $connection->exists) {
            return back()->with('error', __('carddav.flash.password_required'))->withInput();
        }

        // Server-Wechsel: gewähltes Adressbuch, Sync-Stand und Spiegel verwerfen.
        if ($connection->exists && $connection->base_url !== $baseUrl) {
            $attributes['addressbook_url'] = null;
            $attributes['addressbook_name'] = null;
            $attributes['sync_token'] = null;
            CardDavCard::query()->where('carddav_connection_id', $connection->id)->delete();
        }

        $connection->forceFill($attributes)->save();
        $connection->audit('carddav.connection_saved', [
            'by_user_id' => (int) $admin->id,
            'active' => $connection->active,
            'allow_private_network' => $connection->allow_private_network,
        ]);

        return back()->with('success', __('carddav.flash.saved'));
    }

    /** RFC-6764-Discovery: Adressbücher auflisten und zur Wahl stellen. */
    public function discover(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CardDavConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof CardDavConnection || ! $connection->isActive()) {
            return back()->with('error', __('carddav.flash.no_connection'));
        }

        try {
            $books = app(CardDavGatewayFactory::class)->for($connection)->discoverAddressbooks();
        } catch (Throwable) {
            return back()->with('error', __('carddav.flash.discovery_failed'));
        }

        if ($books === []) {
            return back()->with('error', __('carddav.flash.no_addressbooks'));
        }

        session()->put(self::SESSION_BOOKS, array_map(
            static fn(CardDavAddressbook $book): array => ['url' => $book->url, 'name' => $book->name],
            $books,
        ));

        return back()->with('success', __('carddav.flash.discovered', ['count' => count($books)]));
    }

    /** Übernimmt ein Adressbuch aus dem letzten Discovery-Ergebnis als Sync-Quelle. */
    public function chooseAddressbook(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CardDavConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof CardDavConnection) {
            return back()->with('error', __('carddav.flash.no_connection'));
        }

        $data = $request->validate([
            'addressbook_url' => ['required', 'string', 'max:255'],
        ]);

        // Nur Ergebnisse der letzten Discovery zulassen (SSRF-Leitplanke).
        $books = (array) session(self::SESSION_BOOKS, []);
        $chosen = collect($books)->first(
            static fn(array $book): bool => ($book['url'] ?? null) === $data['addressbook_url'],
        );
        if ($chosen === null) {
            return back()->with('error', __('carddav.flash.addressbook_not_discovered'));
        }

        // Quellen-Wechsel: Sync-Stand und Spiegel gehören zum alten Adressbuch.
        if ($connection->addressbook_url !== $chosen['url']) {
            $connection->sync_token = null;
            CardDavCard::query()->where('carddav_connection_id', $connection->id)->delete();
        }

        $connection->forceFill([
            'addressbook_url' => (string) $chosen['url'],
            'addressbook_name' => (string) ($chosen['name'] ?? ''),
        ])->save();
        $connection->audit('carddav.addressbook_chosen', [
            'by_user_id' => (int) $admin->id,
            'addressbook_url' => $connection->addressbook_url,
        ]);
        session()->forget(self::SESSION_BOOKS);

        return back()->with('success', __('carddav.flash.addressbook_saved'));
    }

    /** Manueller Sync (Scheduler-Äquivalent, auditiert). */
    public function sync(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CardDavConnection::query()->where('organization_id', $organization->id)->first();
        if (! $connection instanceof CardDavConnection || ! $connection->isSyncable()) {
            return back()->with('error', __('carddav.flash.not_syncable'));
        }

        Artisan::call('carddav:sync', ['--organization' => (string) $organization->id]);
        $connection->audit('carddav.sync_manual', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('carddav.flash.sync_done'));
    }

    /** Deaktiviert die Anbindung; bereits eingespeiste Inbox-Vorschläge bleiben erhalten. */
    public function disconnect(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = CardDavConnection::query()->where('organization_id', $organization->id)->first();
        if ($connection instanceof CardDavConnection) {
            $connection->forceFill(['active' => false])->save();
            $connection->audit('carddav.disconnected', ['by_user_id' => (int) $admin->id]);
        }

        return back()->with('success', __('carddav.flash.disconnected'));
    }

    /** @return array{ok: bool} */
    private function probe(CardDavConnection $connection): array {
        try {
            return ['ok' => app(CardDavGatewayFactory::class)->for($connection)->ping()];
        } catch (Throwable) {
            return ['ok' => false];
        }
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
