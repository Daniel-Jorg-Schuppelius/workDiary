<?php
/*
 * Created on   : Sun Jul 05 2026
 * Author       : Daniel Jörg Schuppelius
 * Author Uri   : https://schuppelius.org
 * Filename     : MailAdminController.php
 * License      : AGPL-3.0-or-later
 * License Uri  : https://www.gnu.org/licenses/agpl-3.0.html
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Customer, EmailConnection, IntegrationInboxItem, Organization, User};
use App\Services\Mail\{MailInboxResolutionService, MailIntakeService};
use App\Services\SqidEncoder;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Artisan, Auth};
use Illuminate\View\View;
use Throwable;

/**
 * Admin-Verwaltung der E-Mail-Eingangspostfächer (Feature 056, MVP-117):
 * IMAP-Postfächer je Organisation anlegen/bearbeiten/deaktivieren, manuell
 * abrufen und einen zugeordneten Mail-Inbox-Eintrag als Kommunikationsnotiz
 * auflösen. Das Passwort erscheint nie in Views/Audit ({@see EmailConnection::$hidden});
 * ein leeres Passwortfeld lässt das bestehende Passwort unangetastet.
 */
class MailAdminController extends Controller {
    public function index(): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        return view('admin.mail.index', [
            'connections' => EmailConnection::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(),
            'openCount' => IntegrationInboxItem::query()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', MailIntakeService::PLUGIN_ID)
                ->where('status', IntegrationInboxItem::STATUS_OPEN)
                ->count(),
        ]);
    }

    /** Legt ein Postfach an oder aktualisiert es (Passwort nur bei Eingabe). */
    public function store(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'connection' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'max:190'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', 'in:ssl,tls,none'],
            'username' => ['required', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'folder' => ['required', 'string', 'max:190'],
            'processed_folder' => ['nullable', 'string', 'max:190'],
            'active' => ['nullable', 'boolean'],
            'einvoice_intake' => ['nullable', 'boolean'],
        ]);

        $connection = $this->resolveConnectionForEdit($organization, $data['connection'] ?? null);

        $attributes = [
            'organization_id' => $organization->id,
            'name' => (string) $data['name'],
            'host' => trim((string) $data['host']),
            'port' => (int) $data['port'],
            'encryption' => (string) $data['encryption'],
            'username' => (string) $data['username'],
            'folder' => trim((string) $data['folder']) ?: 'INBOX',
            'processed_folder' => filled($data['processed_folder'] ?? null) ? trim((string) $data['processed_folder']) : null,
            'active' => (bool) ($data['active'] ?? false),
            'einvoice_intake' => (bool) ($data['einvoice_intake'] ?? false),
            'created_by' => $connection->exists ? $connection->created_by : $admin->id,
        ];

        $password = trim((string) ($data['password'] ?? ''));
        if ($password !== '') {
            $attributes['password'] = $password;
        } elseif (! $connection->exists) {
            return back()->with('error', __('mail.flash.password_required'))->withInput();
        }

        $connection->forceFill($attributes)->save();
        $connection->audit('mail.connection_saved', ['by_user_id' => (int) $admin->id, 'active' => $connection->active]);

        return back()->with('success', __('mail.flash.saved'));
    }

    /** Deaktiviert ein Postfach (kein Abruf mehr). */
    public function disconnect(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = $this->resolveConnection($organization, (string) $request->input('connection', ''));
        $connection->forceFill(['active' => false])->save();
        $connection->audit('mail.disconnected', ['by_user_id' => (int) $admin->id]);

        return back()->with('success', __('mail.flash.disconnected'));
    }

    /** Manueller Abruf aller Postfächer der Organisation. */
    public function poll(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        Artisan::call('mail:poll', ['--organization' => (string) $organization->id]);

        return back()->with('success', __('mail.flash.polled'));
    }

    /** Löst einen zugeordneten Mail-Inbox-Eintrag als Kommunikationsnotiz auf. */
    public function book(Request $request, MailInboxResolutionService $resolver): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'item' => ['required', 'string'],
            'customer' => ['nullable', 'string'],
        ]);

        $item = $this->resolveInboxItem($organization, (string) $data['item']);
        $customer = $this->resolveCustomer($organization, $data['customer'] ?? null, $item);
        if (! $customer instanceof Customer) {
            return back()->with('error', __('mail.flash.customer_required'));
        }

        try {
            $resolver->bookAsCommunicationNote($item, $customer, $admin);
        } catch (Throwable) {
            return back()->with('error', __('mail.flash.book_failed'));
        }

        return back()->with('success', __('mail.flash.booked'));
    }

    /**
     * „Als Service-Ticket buchen" (MVP-343): erzeugt aus einem zugeordneten
     * Mail-Inbox-Eintrag ein Ticket (Feature 065, Source E-Mail) und schließt
     * den Fall. Kunde optional — leer nutzt den erkannten Absender-Kandidaten.
     */
    public function bookTicket(Request $request, MailInboxResolutionService $resolver): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'item' => ['required', 'string'],
            'customer' => ['nullable', 'string'],
        ]);

        $item = $this->resolveInboxItem($organization, (string) $data['item']);

        // Idempotenz: ein bereits aufgelöster Fall erzeugt kein zweites Ticket.
        if (! $item->isOpen()) {
            return back()->with('error', __('mail.flash.already_resolved'));
        }

        $customer = $this->resolveCustomer($organization, $data['customer'] ?? null, $item);

        try {
            $resolver->bookAsServiceTicket($item, $customer, $admin);
        } catch (Throwable) {
            return back()->with('error', __('mail.flash.ticket_failed'));
        }

        return back()->with('success', __('mail.flash.ticket_booked'));
    }

    /**
     * „Anhänge ins DMS übernehmen" (MVP-343): übernimmt die beim Intake
     * persistierten Anhänge als Dokumente (idempotent je Message-ID+Index),
     * verortet am erkannten bzw. gewählten Kunden.
     */
    public function importDms(Request $request, MailInboxResolutionService $resolver): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $data = $request->validate([
            'item' => ['required', 'string'],
            'customer' => ['nullable', 'string'],
        ]);

        $item = $this->resolveInboxItem($organization, (string) $data['item']);
        $customer = $this->resolveCustomer($organization, $data['customer'] ?? null, $item);

        try {
            $documents = $resolver->importAttachmentsToDms($item, $admin, $customer);
        } catch (Throwable) {
            return back()->with('error', __('mail.flash.dms_failed'));
        }

        if ($documents === []) {
            return back()->with('error', __('mail.dms.none'));
        }

        return back()->with('success', __('mail.dms.imported', ['count' => count($documents)]));
    }

    // --- intern -----------------------------------------------------------

    private function resolveConnectionForEdit(Organization $organization, ?string $sqid): EmailConnection {
        if (! filled($sqid)) {
            return new EmailConnection();
        }

        return $this->resolveConnection($organization, (string) $sqid);
    }

    private function resolveConnection(Organization $organization, string $sqid): EmailConnection {
        $decoded = app(SqidEncoder::class)->decode(EmailConnection::class, $sqid);
        $connection = $decoded !== null
            ? EmailConnection::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
            : null;
        abort_unless($connection instanceof EmailConnection, 404);

        return $connection;
    }

    private function resolveInboxItem(Organization $organization, string $sqid): IntegrationInboxItem {
        $decoded = app(SqidEncoder::class)->decode(IntegrationInboxItem::class, $sqid);
        $item = $decoded !== null
            ? IntegrationInboxItem::query()
                ->whereKey($decoded)
                ->where('organization_id', $organization->id)
                ->where('plugin_id', MailIntakeService::PLUGIN_ID)
                ->first()
            : null;
        abort_unless($item instanceof IntegrationInboxItem, 404);

        return $item;
    }

    private function resolveCustomer(Organization $organization, ?string $sqid, IntegrationInboxItem $item): ?Customer {
        if (filled($sqid)) {
            $decoded = app(SqidEncoder::class)->decode(Customer::class, (string) $sqid);

            return $decoded !== null
                ? Customer::query()->whereKey($decoded)->where('organization_id', $organization->id)->first()
                : null;
        }

        // Fallback: der beim Import gefundene Haupt-Kandidat (falls Kunde).
        $candidate = $item->referenceable;

        return $candidate instanceof Customer ? $candidate : null;
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
