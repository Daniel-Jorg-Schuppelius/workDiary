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

use App\Enums\Integration\IntegrationInboxStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer\Customer;
use App\Models\Integration\IntegrationInboxItem;
use App\Models\Mail\EmailConnection;
use App\Models\Platform\{Organization, User};
use App\Services\Communication\Mail\MailToCommunicationNote;
use App\Services\Document\Mail\MailAttachmentsToDms;
use App\Services\Invoicing\Mail\MailToIncomingInvoice;
use App\Services\Licensing\ModuleStatusResolver;
use App\Services\Mail\Contracts\MailboxTransport;
use App\Services\Mail\{MailIntakeService, MailboxCursor, MailboxGateway, MailboxTransports};
use App\Services\ServiceTicket\Mail\MailToServiceTicket;
use App\Support\{ErrorText, SqidEncoder};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Artisan, Auth};
use Illuminate\Validation\Rule;
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
            'transports' => app(MailboxTransports::class)->all(),
            'connections' => EmailConnection::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(),
            'openCount' => IntegrationInboxItem::query()
                ->where('organization_id', $organization->id)
                ->where('plugin_id', MailIntakeService::PLUGIN_ID)
                ->where('status', IntegrationInboxStatus::Open)
                ->count(),
        ]);
    }

    /** Dialog: neues Postfach; der Transport steht fest, damit nur seine Felder erscheinen. */
    public function create(Request $request): View {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $transport = (string) $request->query('transport', EmailConnection::TRANSPORT_IMAP);
        abort_unless(in_array($transport, $this->transportKeys(), true), 404);

        $connection = new EmailConnection([
            'transport' => $transport,
            'folder' => 'INBOX',
            'active' => true,
            'fetch_since' => Carbon::today(),
        ]);
        $connection->organization_id = $organization->id;

        return $this->formDialog($organization, $connection);
    }

    public function edit(EmailConnection $connection): View {
        $admin = $this->admin();

        return $this->formDialog($this->organization($admin), $this->owned($admin, $connection));
    }

    public function store(Request $request): RedirectResponse {
        $admin = $this->admin();

        return $this->save($request, $admin, new EmailConnection());
    }

    public function update(Request $request, EmailConnection $connection): RedirectResponse {
        $admin = $this->admin();

        return $this->save($request, $admin, $this->owned($admin, $connection));
    }

    /** Dialog: ältere Mails ab einem Datum nachholen (MVP-1143). */
    public function backfillForm(EmailConnection $connection): View {
        $admin = $this->admin();

        return view('admin.mail._backfill_dialog', ['connection' => $this->owned($admin, $connection)]);
    }

    /**
     * Nachholen = Startpunkt zurückstellen und den Stand verwerfen (E3); die
     * nächsten Läufe arbeiten ab dem Datum, nur im Ordner dieses Eintrags.
     */
    public function backfill(Request $request, EmailConnection $connection): RedirectResponse {
        $admin = $this->admin();
        $connection = $this->owned($admin, $connection);

        $data = $request->validate([
            'fetch_since' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $connection->forceFill([
            'fetch_since' => Carbon::parse((string) $data['fetch_since'])->startOfDay(),
            'sync_cursor' => MailboxCursor::start($connection, true)->toArray(),
        ])->save();
        $connection->audit('mail.backfill_started', ['by_user_id' => (int) $admin->id, 'fetch_since' => $connection->fetch_since?->toDateString()]);

        return redirect()->toList('admin.mail.index')->with('success', __('mail.flash.backfill_started'));
    }

    public function activate(EmailConnection $connection): RedirectResponse {
        $admin = $this->admin();
        $connection = $this->owned($admin, $connection);

        $connection->forceFill(['active' => true])->save();
        $connection->audit('mail.connection_activated', ['by_user_id' => (int) $admin->id]);

        return redirect()->toList('admin.mail.index')->with('success', __('mail.flash.activated'));
    }

    /** Deaktiviert ein Postfach (kein Abruf mehr). */
    public function disconnect(Request $request): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        $connection = $this->resolveConnection($organization, (string) $request->input('connection', ''));
        $connection->forceFill(['active' => false])->save();
        $connection->audit('mail.disconnected', ['by_user_id' => (int) $admin->id]);

        return redirect()->toList('admin.mail.index')->with('success', __('mail.flash.disconnected'));
    }

    /** Manueller Abruf aller Postfächer der Organisation. */
    public function poll(): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);

        // Queue statt Request (Vollscan 2026-08-23, J17): ein Voll-Sync im Web-
        // Request lief in den PHP-Timeout; der Worker hat Retry und Laufzeitbudget.
        Artisan::queue('mail:poll', ['--organization' => (string) $organization->id]);

        return back()->with('success', __('mail.flash.polled'));
    }

    /** Löst einen zugeordneten Mail-Inbox-Eintrag als Kommunikationsnotiz auf. */
    public function book(Request $request, MailToCommunicationNote $resolver): RedirectResponse {
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
    public function bookTicket(Request $request, MailToServiceTicket $resolver): RedirectResponse {
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
    public function importDms(Request $request, MailAttachmentsToDms $resolver): RedirectResponse {
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

    /**
     * „Als Eingangsrechnung übernehmen“ (MVP-1144): die abgelegten Anhänge
     * eines Mail-Eintrags laufen durch den Rechnungseingang, auch aus
     * Postfächern ohne Rechnungsschalter.
     */
    public function importInvoice(Request $request, MailToIncomingInvoice $resolver, ModuleStatusResolver $modules): RedirectResponse {
        $admin = $this->admin();
        $organization = $this->organization($admin);
        abort_unless($admin->canManageBilling() && $modules->isActiveFor($organization, 'module.finance'), 403);

        $data = $request->validate([
            'item' => ['required', 'string'],
        ]);
        $item = $this->resolveInboxItem($organization, (string) $data['item']);
        if (! $item->isOpen()) {
            return back()->with('error', __('mail.flash.already_resolved'));
        }

        try {
            $result = $resolver->import($item, $admin);
        } catch (Throwable) {
            return back()->with('error', __('mail.flash.invoice_failed'));
        }

        return match (true) {
            $result['stored'] + $result['unrecognized'] > 0 => back()->with('success', __('mail.flash.invoice_imported', ['count' => $result['stored'] + $result['unrecognized']])),
            $result['duplicates'] > 0 => back()->with('success', __('mail.flash.invoice_duplicate')),
            default => back()->with('error', __('mail.flash.invoice_none')),
        };
    }

    // --- intern -----------------------------------------------------------

    private function formDialog(Organization $organization, EmailConnection $connection): View {
        $transport = $connection->usesImap() ? null : app(MailboxTransports::class)->get((string) $connection->transport);
        $unavailable = $transport?->unavailableReason($organization);

        // Ordnerauswahl (E8): bei M365 sofort, bei IMAP erst mit gespeicherten Zugangsdaten.
        $folders = [];
        $foldersError = null;
        if ($unavailable === null && ($transport !== null || $connection->exists)) {
            try {
                $folders = app(MailboxGateway::class)->folders($connection);
            } catch (Throwable $e) {
                $foldersError = ErrorText::for($e);
            }
        }

        return view('admin.mail._form_dialog', [
            'connection' => $connection,
            'transport' => $transport,
            'unavailable' => $unavailable,
            'folders' => $folders,
            'foldersError' => $foldersError,
        ]);
    }

    /**
     * Gemeinsamer Speicherweg für Anlegen und Bearbeiten. Ein leeres
     * Passwortfeld lässt das bestehende Passwort; Ordner-, Adress- oder
     * Startpunktwechsel verwerfen den Abrufstand (MVP-1139).
     */
    private function save(Request $request, User $admin, EmailConnection $connection): RedirectResponse {
        $organization = $this->organization($admin);
        $transport = $connection->exists
            ? (string) ($connection->transport ?: EmailConnection::TRANSPORT_IMAP)
            : (string) $request->input('transport', EmailConnection::TRANSPORT_IMAP);
        $imap = $transport === EmailConnection::TRANSPORT_IMAP;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'transport' => ['nullable', Rule::in($this->transportKeys())],
            'host' => [Rule::requiredIf($imap), 'nullable', 'string', 'max:190'],
            'port' => [Rule::requiredIf($imap), 'nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => [Rule::requiredIf($imap), 'nullable', 'in:ssl,tls,none'],
            'username' => [Rule::requiredIf($imap), 'nullable', 'string', 'max:190'],
            'password' => ['nullable', 'string', 'max:255'],
            'mailbox_address' => ['nullable', 'email', 'max:190'],
            'folder' => ['required', 'string', 'max:190'],
            'processed_folder' => ['nullable', 'string', 'max:190', 'different:folder'],
            'fetch_since' => ['nullable', 'date', 'before_or_equal:today'],
            'active' => ['nullable', 'boolean'],
            'einvoice_intake' => ['nullable', 'boolean'],
            'callreport_intake' => ['nullable', 'boolean'],
        ]);

        $unavailable = $imap ? null : app(MailboxTransports::class)->get($transport)?->unavailableReason($organization);
        if ($unavailable !== null) {
            return back()->with('error', $unavailable)->withInput();
        }

        $attributes = [
            'organization_id' => $organization->id,
            'name' => (string) $data['name'],
            'transport' => $transport,
            'host' => $imap ? trim((string) $data['host']) : null,
            'port' => $imap ? (int) $data['port'] : 0,
            'encryption' => $imap ? (string) $data['encryption'] : 'none',
            'username' => $imap ? (string) $data['username'] : null,
            'mailbox_address' => $imap || ! filled($data['mailbox_address'] ?? null) ? null : mb_strtolower(trim((string) $data['mailbox_address'])),
            'folder' => trim((string) $data['folder'], " \t/") ?: 'INBOX',
            'processed_folder' => filled($data['processed_folder'] ?? null) ? trim((string) $data['processed_folder'], " \t/") : null,
            'fetch_since' => filled($data['fetch_since'] ?? null) ? Carbon::parse((string) $data['fetch_since'])->startOfDay() : ($connection->fetch_since ?? Carbon::today()),
            'active' => (bool) ($data['active'] ?? false),
            'einvoice_intake' => (bool) ($data['einvoice_intake'] ?? false),
            'callreport_intake' => (bool) ($data['callreport_intake'] ?? false),
            'created_by' => $connection->exists ? $connection->created_by : $admin->id,
        ];

        $password = trim((string) ($data['password'] ?? ''));
        if (! $imap) {
            $attributes['password'] = null;
        } elseif ($password !== '') {
            $attributes['password'] = $password;
        } elseif (! $connection->exists) {
            return back()->withErrors(['password' => __('mail.flash.password_required')])->withInput();
        }

        $candidate = (clone $connection)->forceFill($attributes);
        $siblings = EmailConnection::query()
            ->where('organization_id', $organization->id)
            ->when($connection->exists, fn ($query) => $query->whereKeyNot($connection->getKey()))
            ->get()
            ->filter(fn (EmailConnection $other): bool => self::sameAccess($other, $candidate));
        if ($siblings->contains(fn (EmailConnection $other): bool => self::sameFolder((string) $other->folder, (string) $candidate->folder))) {
            return back()->withErrors(['folder' => __('mail.validation.duplicate_folder')])->withInput();
        }

        $previousSince = $connection->fetch_since?->copy();
        $restart = $connection->exists && (
            ! self::sameFolder((string) $connection->folder, (string) $attributes['folder'])
            || $connection->mailbox_address !== $attributes['mailbox_address']
            || ! $previousSince?->equalTo($attributes['fetch_since'])
        );

        $connection->forceFill($attributes)->save();
        if ($restart) {
            $backfill = $previousSince !== null && $attributes['fetch_since']->lessThan($previousSince);
            $connection->forceFill(['sync_cursor' => MailboxCursor::start($connection, $backfill)->toArray()])->save();
        }
        $connection->audit('mail.connection_saved', ['by_user_id' => (int) $admin->id, 'active' => $connection->active]);

        $redirect = redirect()->toList('admin.mail.index')->with('success', __('mail.flash.saved'));
        $chained = $siblings->first(fn (EmailConnection $other): bool => ($connection->processed_folder !== null && self::sameFolder((string) $connection->processed_folder, (string) $other->folder))
            || ($other->processed_folder !== null && self::sameFolder((string) $other->processed_folder, (string) $connection->folder)));

        return $chained instanceof EmailConnection
            ? $redirect->with('warning', __('mail.flash.chained_folders', ['name' => $chained->name]))
            : $redirect;
    }

    /** Gleicher Zugang (E19): IMAP-Benutzer und -Server bzw. dasselbe M365-Postfach. */
    private static function sameAccess(EmailConnection $a, EmailConnection $b): bool {
        if ($a->usesImap() !== $b->usesImap()) {
            return false;
        }
        if ($a->usesImap()) {
            return mb_strtolower(trim((string) $a->username)) === mb_strtolower(trim((string) $b->username))
                && mb_strtolower(trim((string) $a->host)) === mb_strtolower(trim((string) $b->host));
        }

        return $a->transport === $b->transport
            && mb_strtolower(trim((string) $a->mailbox_address)) === mb_strtolower(trim((string) $b->mailbox_address));
    }

    /** Ordnerpfade gleich, `INBOX` ohne Rücksicht auf die Schreibweise. */
    private static function sameFolder(string $a, string $b): bool {
        $normalize = static fn (string $path): string => preg_replace('/^inbox(?=\/|$)/i', 'INBOX', trim($path, " \t/")) ?? $path;

        return $normalize($a) === $normalize($b);
    }

    /** @return list<string> */
    private function transportKeys(): array {
        return [EmailConnection::TRANSPORT_IMAP, ...array_map(static fn (MailboxTransport $t): string => $t->key(), app(MailboxTransports::class)->all())];
    }

    private function owned(User $admin, EmailConnection $connection): EmailConnection {
        abort_unless((int) $connection->organization_id === (int) $admin->organization_id, 404);

        return $connection;
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
